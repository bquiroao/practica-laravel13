<?php

namespace Tests\Feature\Clinical;

use App\Models\Hospital\Bed;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\LocalPatient;
use App\Models\Hospital\Ward;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;

/**
 * Módulo admisión hospitalaria y asignación de cama: CU-01 a CU-06 y las reglas RN-01 a RN-06.
 * Demostración: MED-1/201, CIR-1/301 y PED-1/101 ocupadas; CIR-1/304 en mantenimiento.
 */
#[Group('clinical')]
class AdmissionTest extends ClinicalTestCase
{
    /** @return list<LocalPatient> */
    private function patients(): array
    {
        return LocalPatient::query()->orderBy('code')->get()->all();
    }

    private function bed(string $ward, string $code): Bed
    {
        return Bed::query()->whereHas('ward', fn ($q) => $q->where('code', $ward))->where('code', $code)->firstOrFail();
    }

    private function payload(LocalPatient $patient, Bed $bed, array $extra = []): array
    {
        return array_merge(['patient_id' => $patient->local_patient_id, 'bed_id' => $bed->bed_id, 'bed_type' => 'standard', 'isolation' => false,
            'source' => 'emergencia', 'reason' => 'Neumonía, requiere oxígeno'], $extra);
    }

    public function test_admission_opens_the_inpatient_stay_and_leaves_its_trace(): void
    {
        $request = '6f1c2a3b-4d5e-4f60-8a7b-9c0d1e2f3a4b';
        $headers = $this->as('recepcion@nido.test') + ['X-Request-Id' => $request];
        $patient = $this->patients()[0];
        $bed = $this->bed('MED-1', '202');

        $response = $this->postJson('/api/v1/admisiones', $this->payload($patient, $bed), $headers)->assertCreated()
            ->assertHeader('X-Request-Id', $request)->assertHeader('Location')
            ->assertJsonPath('data.status', 'activa')->assertJsonPath('data.patient.code', $patient->code)
            ->assertJsonPath('data.bed.code', '202')->assertJsonPath('data.bed.ward.code', 'MED-1')->assertJsonPath('data.source', 'emergencia');
        $this->assertArrayNotHasKey('name', $response->json('data.patient'), 'RNF-03: solo el código del paciente');
        $id = $response->json('data.id');

        $episode = CareEpisode::query()->findOrFail($id);
        $this->assertSame(['inpatient', 'open', 'MED-1', 'emergencia'], [$episode->type, $episode->status, $episode->service_code, $episode->admission_source]);
        $this->assertSame('occupied', $bed->fresh()->status, 'La cama queda ocupada en la misma transacción');

        // RN-05: dos eventos en orden, con la solicitud que los originó.
        $events = $this->getJson("/api/v1/admisiones/{$id}/eventos", $headers)->assertOk()->json('data');
        $this->assertSame(['ADMISION_CREADA', 'CAMA_ASIGNADA'], array_column($events, 'accion'));
        $this->assertSame($request, $events[0]['solicitud']);
        $this->assertSame('recepcion', $events[0]['detalle']['rol']);

        // El mapa de camas lo muestra, y traslados y altas trabajan sobre esa estancia.
        $medicine = collect($this->getJson('/api/v1/camas', $headers)->json('data'))->firstWhere('code', 'MED-1');
        $this->assertSame($id, collect($medicine['beds'])->firstWhere('code', '202')['occupant']['episode_id']);
        $this->travel(3)->hours();
        $headers = $this->as('recepcion@nido.test'); // la sesión dura 15 minutos
        $this->postJson("/api/v1/internamientos/{$id}/alta", ['type' => 'medica', 'reason' => 'Mejoría, control en 7 días'], $this->as('medico@nido.test'))->assertOk();
        $this->assertSame(['ADMISION_CREADA', 'CAMA_ASIGNADA', 'ALTA'], array_column($this->getJson("/api/v1/admisiones/{$id}/eventos", $headers)->json('data'), 'accion'));
        $this->getJson("/api/v1/admisiones/{$id}", $headers)->assertOk()->assertJsonPath('data.status', 'cerrada')->assertJsonPath('data.discharge_type', 'Alta médica');
    }

    public function test_available_beds_are_operational_free_and_compatible(): void
    {
        $headers = $this->as('recepcion@nido.test');
        $medicine = Ward::query()->where('code', 'MED-1')->value('ward_id');
        Bed::query()->create(['ward_id' => $medicine, 'code' => 'A-1', 'type' => 'aislamiento', 'status' => 'available']);

        // General: MED-1 202–206 y CIR-1 302–303. No salen 201 ni 301 (ocupadas) ni 304 (mantenimiento).
        $general = $this->getJson('/api/v1/camas-disponibles?bed_type=standard&isolation=0', $headers)->assertOk()->assertJsonPath('total', 7)->json('data');
        $this->assertNotContains('201', array_column($general, 'code'));
        $this->assertNotContains('304', array_column($general, 'code'));
        $this->getJson("/api/v1/camas-disponibles?bed_type=standard&isolation=0&ward_id={$medicine}", $headers)->assertOk()->assertJsonPath('total', 5);
        // RN-03: con aislamiento solo vale la cama de aislamiento, y esa cama no sale para quien no lo requiere.
        $this->getJson('/api/v1/camas-disponibles?bed_type=standard&isolation=1', $headers)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.code', 'A-1');
        $this->getJson('/api/v1/camas-disponibles?bed_type=aislamiento&isolation=0', $headers)->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/camas-disponibles?bed_type=uci&isolation=0', $headers)->assertOk()->assertJsonPath('total', 3);
        $this->getJson('/api/v1/camas-disponibles?bed_type=suite', $headers)->assertStatus(422)->assertJsonValidationErrors(['bed_type', 'isolation']);
    }

    public function test_each_rule_is_rejected_with_its_code(): void
    {
        $headers = $this->as('recepcion@nido.test');
        $patients = $this->patients();
        $free = $this->bed('MED-1', '203');

        // RN-02: el paciente 4 de la demostración ya está internado en MED-1/201.
        $this->postJson('/api/v1/admisiones', $this->payload($patients[3], $free), $headers)->assertStatus(409)->assertJsonPath('code', 'PACIENTE_CON_ADMISION_ACTIVA');
        // RN-01: la cama ya tiene una estancia abierta.
        $this->postJson('/api/v1/admisiones', $this->payload($patients[0], $this->bed('MED-1', '201')), $headers)->assertStatus(409)->assertJsonPath('code', 'CAMA_YA_ASIGNADA');
        // RN-04: cama en mantenimiento.
        $this->postJson('/api/v1/admisiones', $this->payload($patients[0], $this->bed('CIR-1', '304')), $headers)
            ->assertStatus(422)->assertJsonPath('code', 'CAMA_NO_ELEGIBLE')->assertJsonPath('details.motivos.0', 'La cama no está disponible (en mantenimiento o inactiva).');
        // RN-03: tipo de cama y sala pedida.
        $this->postJson('/api/v1/admisiones', $this->payload($patients[0], $this->bed('UCI', 'U-1')), $headers)->assertStatus(422)->assertJsonPath('code', 'CAMA_NO_ELEGIBLE');
        $this->postJson('/api/v1/admisiones', $this->payload($patients[0], $free, ['ward_id' => Ward::query()->where('code', 'CIR-1')->value('ward_id')]), $headers)
            ->assertStatus(422)->assertJsonPath('details.motivos.0', 'La cama es de otra sala.');
        $this->postJson('/api/v1/admisiones', $this->payload($patients[0], $free, ['isolation' => true]), $headers)
            ->assertStatus(422)->assertJsonPath('details.motivos.0', 'El paciente requiere una cama de aislamiento.');
        // Paciente y cama que no existen, y paciente que no está activo.
        $this->postJson('/api/v1/admisiones', $this->payload($patients[0], $free, ['bed_id' => '00000000-0000-4000-8000-000000000000']), $headers)
            ->assertNotFound()->assertJsonPath('code', 'CAMA_NO_ENCONTRADA');
        $patients[5]->forceFill(['status' => 'inactive'])->save();
        $this->postJson('/api/v1/admisiones', $this->payload($patients[5], $free), $headers)->assertNotFound()->assertJsonPath('code', 'PACIENTE_NO_ENCONTRADO');
        // Forma de la petición: campos que faltan y el hospital en el cuerpo (sale de la sesión).
        $this->postJson('/api/v1/admisiones', ['tenant_id' => 'otro'], $headers)->assertStatus(422)
            ->assertJsonValidationErrors(['patient_id', 'bed_id', 'bed_type', 'isolation', 'source', 'reason', 'tenant_id']);
        $this->getJson('/api/v1/admisiones/00000000-0000-4000-8000-000000000000', $headers)->assertNotFound()->assertJsonPath('code', 'ADMISION_NO_ENCONTRADA');

        // Nada de lo rechazado dejó rastro: la cama sigue libre y no hay internamientos nuevos.
        $this->assertSame('available', $free->fresh()->status);
        $this->assertSame(3, CareEpisode::query()->where('type', 'inpatient')->count());
    }

    public function test_the_database_keeps_one_open_stay_per_bed_and_marks_it_occupied(): void
    {
        $id = $this->postJson('/api/v1/admisiones', $this->payload($this->patients()[0], $this->bed('MED-1', '206')), $this->as('recepcion@nido.test'))
            ->assertCreated()->json('data.id');
        $stay = DB::connection('hospital')->table('bed_stays')->where('care_episode_id', $id)->first();

        // RN-01: aunque dos peticiones pasen la validación a la vez, la base rechaza la segunda estancia abierta en la cama.
        try {
            DB::connection('hospital')->insert("INSERT INTO bed_stays (care_episode_id, bed_id, assigned_by_user_ref_id, started_at, reason) VALUES (?, ?, ?, now(), 'Duplicada')",
                [CareEpisode::query()->where('type', 'inpatient')->where('care_episode_id', '<>', $id)->value('care_episode_id'), $stay->bed_id, $stay->assigned_by_user_ref_id]);
            $this->fail('Se guardó una segunda estancia abierta en la misma cama');
        } catch (QueryException $e) {
            $this->assertStringContainsString('bed_stays', $e->getMessage());
        }
        // Defecto AU-05 cerrado: la cama con estancia abierta figura «occupied».
        $this->assertSame('occupied', DB::connection('hospital')->table('beds')->where('bed_id', $stay->bed_id)->value('status'));
    }

    public function test_admissions_feed_the_admissions_indicator_by_source(): void
    {
        $patients = $this->patients();
        $this->postJson('/api/v1/admisiones', $this->payload($patients[0], $this->bed('MED-1', '205'), ['source' => 'referencia']), $this->as('recepcion@nido.test'))->assertCreated();
        $today = now('America/Guatemala')->toDateString();

        $parts = collect($this->getJson("/api/v1/reportes/ingresos/desglose?desde={$today}&hasta={$today}&dimension=origen", $this->as('direccion@nido.test'))
            ->assertOk()->json('datos.desglose.partes'))->pluck('valor', 'parte');
        $this->assertSame(1, $parts['Referencia de otro centro'] ?? null, 'El origen es administrativo y no se agrupa como «Otros»');
    }

    public function test_only_roles_with_the_permission_admit(): void
    {
        $patient = $this->patients()[0];
        $bed = $this->bed('MED-1', '204');

        $this->postJson('/api/v1/admisiones', $this->payload($patient, $bed), $this->as('enfermeria@nido.test'))->assertForbidden()->assertJsonPath('code', 'PERMISO_NO_ASIGNADO');
        $this->getJson('/api/v1/camas-disponibles?bed_type=standard&isolation=0', $this->as('laboratorio@nido.test'))->assertForbidden();

        $id = $this->postJson('/api/v1/admisiones', $this->payload($patient, $bed), $this->as('medico@nido.test'))->assertCreated()->json('data.id');
        // Enfermería consulta la admisión (ve camas), pero no su trazabilidad.
        $this->getJson("/api/v1/admisiones/{$id}", $this->as('enfermeria@nido.test'))->assertOk();
        $this->getJson("/api/v1/admisiones/{$id}/eventos", $this->as('enfermeria@nido.test'))->assertForbidden();
        $this->getJson("/api/v1/admisiones/{$id}/eventos", $this->as('calidad@nido.test'))->assertOk()->assertJsonCount(2, 'data');
    }
}

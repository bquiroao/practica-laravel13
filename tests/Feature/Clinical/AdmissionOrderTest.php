<?php

namespace Tests\Feature\Clinical;

use App\Models\Central\AuditLog;
use App\Models\Hospital\AdmissionOrder;
use App\Models\Hospital\Bed;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\LocalPatient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;

/**
 * Mejora del módulo admisión: la orden de ingreso pendiente de cama y el tiempo de espera de cama.
 * Demostración: MED-1/202 es una cama general libre, PED-1/102 una pediátrica libre y Hernández Castillo ya está internado.
 */
#[Group('clinical')]
class AdmissionOrderTest extends ClinicalTestCase
{
    private const URL = '/api/v1/admisiones/ordenes';

    private function patient(string $family): LocalPatient
    {
        return LocalPatient::query()->where('family_name', $family)->firstOrFail();
    }

    private function bed(string $ward, string $code): Bed
    {
        return Bed::query()->whereHas('ward', fn ($q) => $q->where('code', $ward))->where('code', $code)->firstOrFail();
    }

    private function order(LocalPatient $patient, array $extra = [], ?array $headers = null): string
    {
        return $this->postJson(self::URL, $extra + ['patient_id' => $patient->local_patient_id, 'bed_type' => 'standard', 'isolation' => false,
            'source' => 'emergencia', 'priority' => 'urgente', 'reason' => 'Neumonía, requiere oxígeno'], $headers ?? $this->as('medico@nido.test'))
            ->assertCreated()->json('data.id');
    }

    public function test_an_order_puts_the_patient_on_the_waiting_list_once(): void
    {
        $doctor = $this->as('medico@nido.test');
        $id = $this->order($this->patient('Ramírez Ortiz'), ['priority' => 'programada']);
        $this->order($this->patient('Gómez Ruiz'));

        // La lista de espera: urgentes primero.
        $list = $this->getJson(self::URL, $this->as('recepcion@nido.test'))->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertSame(['urgente', 'programada'], array_column($list, 'priority'));
        $this->assertSame(['pendiente', 'Dr. Carlos Ramírez Soto'], [$list[1]['status'], $list[1]['ordered_by']]);
        $this->assertSame($id, $list[1]['id']);
        $this->assertTrue(AuditLog::query()->where('accion', 'ORDEN_INGRESO_CREADA')->exists());

        // Una sola orden pendiente por paciente, y no para quien ya está internado.
        $body = ['patient_id' => $this->patient('Ramírez Ortiz')->local_patient_id, 'bed_type' => 'standard', 'isolation' => false, 'source' => 'emergencia',
            'priority' => 'urgente', 'reason' => 'Otra orden'];
        $this->postJson(self::URL, $body, $doctor)->assertStatus(409)->assertJsonPath('code', 'ORDEN_PENDIENTE')->assertJsonPath('details.orden_id', $id);
        $this->postJson(self::URL, ['patient_id' => $this->patient('Hernández Castillo')->local_patient_id] + $body, $doctor)
            ->assertStatus(409)->assertJsonPath('code', 'PACIENTE_CON_ADMISION_ACTIVA');
        $this->postJson(self::URL, ['reason' => 'no', 'bed_type' => 'suite'] + $body, $doctor)->assertUnprocessable()->assertJsonValidationErrors(['reason', 'bed_type']);
        $this->postJson(self::URL, $body, $this->as('enfermeria@nido.test'))->assertForbidden();

        // La base también lo impide.
        $this->expectException(QueryException::class);
        AdmissionOrder::query()->create(AdmissionOrder::query()->findOrFail($id)->only(['local_patient_id', 'ordered_by_user_ref_id', 'bed_type', 'isolation',
            'source', 'priority', 'reason']) + ['status' => 'pendiente', 'ordered_at' => now()]);
    }

    public function test_assigning_a_bed_admits_the_patient_and_records_the_wait(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        $patient = $this->patient('Ramírez Ortiz');
        $id = $this->order($patient);

        // 2 h 15 min después aparece una cama. Una cama que no sirve se rechaza y la orden sigue pendiente.
        $this->travelTo(now()->setTime(10, 15));
        $reception = $this->as('recepcion@nido.test');
        $this->postJson(self::URL."/{$id}/asignar", ['bed_id' => $this->bed('PED-1', '102')->bed_id], $reception)
            ->assertUnprocessable()->assertJsonPath('code', 'CAMA_NO_ELEGIBLE');
        $this->assertSame('pendiente', AdmissionOrder::query()->findOrFail($id)->status);

        $admission = $this->postJson(self::URL."/{$id}/asignar", ['bed_id' => $this->bed('MED-1', '202')->bed_id], $reception)->assertCreated()
            ->assertHeader('X-Request-Id')->assertJsonPath('data.bed.code', '202')->assertJsonPath('data.source', 'emergencia')->json('data.id');

        $order = AdmissionOrder::query()->findOrFail($id);
        $this->assertSame(['asignada', $admission], [$order->status, $order->care_episode_id]);
        $this->assertSame(135, (int) $order->ordered_at->diffInMinutes($order->assigned_at));
        $this->assertSame('Neumonía, requiere oxígeno', CareEpisode::query()->findOrFail($admission)->reason, 'El internamiento lleva el motivo de la orden');
        $this->assertSame(135, AuditLog::query()->where('accion', 'ADMISION_CREADA')->firstOrFail()->detalle['espera_minutos']);
        $this->getJson(self::URL, $reception)->assertJsonCount(0, 'data');
        $this->getJson(self::URL.'?estado=todas', $reception)->assertJsonPath('data.0.status', 'asignada')->assertJsonPath('data.0.waiting_minutes', 135);

        $this->postJson(self::URL."/{$id}/asignar", ['bed_id' => $this->bed('MED-1', '203')->bed_id], $reception)
            ->assertStatus(409)->assertJsonPath('code', 'ORDEN_NO_PENDIENTE');
    }

    public function test_a_direct_admission_also_fulfils_a_pending_order(): void
    {
        $patient = $this->patient('Gómez Ruiz');
        $id = $this->order($patient);

        $this->postJson('/api/v1/admisiones', ['patient_id' => $patient->local_patient_id, 'bed_id' => $this->bed('MED-1', '203')->bed_id, 'bed_type' => 'standard',
            'isolation' => false, 'source' => 'emergencia', 'reason' => 'Ingreso directo'], $this->as('recepcion@nido.test'))->assertCreated();

        $this->assertSame('asignada', AdmissionOrder::query()->findOrFail($id)->status);
    }

    public function test_an_order_is_cancelled_with_a_reason(): void
    {
        $id = $this->order($this->patient('Ramírez Ortiz'));
        $reception = $this->as('recepcion@nido.test');

        $this->postJson(self::URL."/{$id}/cancelar", ['reason' => 'no'], $reception)->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->postJson(self::URL."/{$id}/cancelar", ['reason' => 'Mejoró y se fue a casa'], $reception)->assertOk()
            ->assertJsonPath('data.status', 'cancelada')->assertJsonPath('data.cancel_reason', 'Mejoró y se fue a casa');
        $this->postJson(self::URL."/{$id}/cancelar", ['reason' => 'Mejoró y se fue a casa'], $reception)->assertStatus(409)->assertJsonPath('code', 'ORDEN_NO_PENDIENTE');
        $this->postJson(self::URL."/{$id}/asignar", ['bed_id' => $this->bed('MED-1', '202')->bed_id], $reception)->assertStatus(409);
        $this->assertTrue(AuditLog::query()->where('accion', 'ORDEN_INGRESO_CANCELADA')->exists());
        $this->postJson(self::URL.'/00000000-0000-4000-8000-000000000000/cancelar', ['reason' => 'No existe'], $reception)
            ->assertNotFound()->assertJsonPath('code', 'ORDEN_NO_ENCONTRADA');
    }

    public function test_the_bed_wait_indicator_averages_the_waits_of_the_period(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        $first = $this->order($this->patient('Ramírez Ortiz'));
        $second = $this->order($this->patient('Gómez Ruiz'), ['priority' => 'programada']);
        $this->travelTo(now()->setTime(9, 0));
        $this->postJson(self::URL."/{$first}/asignar", ['bed_id' => $this->bed('MED-1', '202')->bed_id], $this->as('recepcion@nido.test'))->assertCreated();
        $this->travelTo(now()->setTime(10, 0));
        $this->postJson(self::URL."/{$second}/asignar", ['bed_id' => $this->bed('MED-1', '203')->bed_id], $this->as('recepcion@nido.test'))->assertCreated();

        $day = now('America/Guatemala')->toDateString();
        $director = $this->as('direccion@nido.test');
        $this->getJson("/api/v1/reportes/espera_cama?desde={$day}&hasta={$day}", $director)->assertOk()
            ->assertJsonPath('datos.indicador.unidad', 'minutos')->assertJsonPath('datos.indicador.numerador', 180)
            ->assertJsonPath('datos.indicador.denominador', 2)->assertJsonPath('datos.indicador.valor', 90);
        $parts = $this->getJson("/api/v1/reportes/espera_cama/desglose?desde={$day}&hasta={$day}&dimension=prioridad", $director)->assertOk()
            ->assertJsonPath('datos.desglose.verificado', true)->collect('datos.desglose.partes')->pluck('valor', 'parte');
        $this->assertSame(['Programada' => 120, 'Urgente' => 60], $parts->sortKeys()->all());
        $this->assertSame([], $this->getJson('/api/v1/reportes/indicadores', $director)->json('pendientes'), 'Ya no queda ningún indicador pendiente');
    }

    public function test_the_order_rules_live_in_the_database_too(): void
    {
        $id = $this->order($this->patient('Ramírez Ortiz'));

        $this->expectException(QueryException::class);
        DB::connection('hospital')->table('admission_orders')->where('admission_order_id', $id)->update(['status' => 'cancelada']);
    }
}

<?php

namespace Tests\Feature\Clinical;

use App\Models\Hospital\Bed;
use App\Models\Hospital\BedStay;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\LocalPatient;
use App\Models\Hospital\Ward;
use PHPUnit\Framework\Attributes\Group;

#[Group('clinical')]
class BedTest extends ClinicalTestCase
{
    private function bed(string $ward, string $code): Bed
    {
        return Bed::query()->where('code', $code)->whereHas('ward', fn ($q) => $q->where('code', $ward))->firstOrFail();
    }

    private function episodeOf(string $family): string
    {
        $patient = LocalPatient::query()->where('family_name', $family)->value('local_patient_id');

        return CareEpisode::query()->where('local_patient_id', $patient)->where('status', 'open')->value('care_episode_id');
    }

    public function test_bed_map_shows_wards_beds_and_who_occupies_them(): void
    {
        $data = $this->getJson('/api/v1/camas', $this->as('enfermeria@nido.test'))->assertOk()->json('data');

        $medicine = collect($data)->firstWhere('code', 'MED-1');
        $this->assertCount(6, $medicine['beds']);
        $this->assertSame('José Antonio Hernández Castillo', collect($medicine['beds'])->firstWhere('code', '201')['occupant']['patient']['name']);
    }

    public function test_ward_and_bed_catalog_rules(): void
    {
        $admin = $this->as('admin.hospital@nido.test');
        $this->postJson('/api/v1/salas', ['code' => 'med-1', 'name' => 'Otra', 'location' => 'X'], $admin)->assertStatus(409)->assertJsonPath('code', 'SALA_EXISTENTE');
        $ward = $this->postJson('/api/v1/salas', ['code' => 'obs', 'name' => 'Observación', 'location' => 'Emergencia', 'capacity' => 1], $admin)
            ->assertCreated()->assertJsonPath('data.code', 'OBS')->json('data.id');

        $this->postJson("/api/v1/salas/{$ward}/camas", ['code' => 'o-1', 'type' => 'standard'], $admin)->assertCreated()->assertJsonPath('data.status', 'available');
        $this->postJson("/api/v1/salas/{$ward}/camas", ['code' => 'O-1', 'type' => 'standard'], $admin)->assertStatus(409)->assertJsonPath('code', 'CAMA_EXISTENTE');
        $this->postJson("/api/v1/salas/{$ward}/camas", ['code' => 'O-2', 'type' => 'standard'], $admin)->assertStatus(409)->assertJsonPath('code', 'SALA_LLENA');
    }

    public function test_bed_status_changes_need_a_reason_and_occupied_beds_cannot_be_changed(): void
    {
        $admin = $this->as('admin.hospital@nido.test');
        $free = $this->bed('MED-1', '202')->bed_id;

        $this->patchJson("/api/v1/camas/{$free}/estado", ['status' => 'maintenance'], $admin)->assertStatus(422)->assertJsonPath('code', 'MOTIVO_REQUERIDO');
        $this->patchJson("/api/v1/camas/{$free}/estado", ['status' => 'maintenance', 'reason' => 'Limpieza profunda'], $admin)
            ->assertOk()->assertJsonPath('data.status', 'maintenance')->assertJsonPath('data.next', ['available', 'inactive']);
        $occupied = $this->bed('MED-1', '201')->bed_id;
        $this->patchJson("/api/v1/camas/{$occupied}/estado", ['status' => 'inactive', 'reason' => 'Prueba'], $admin)->assertStatus(409)->assertJsonPath('code', 'CAMA_OCUPADA');
    }

    public function test_transfer_moves_the_patient_and_frees_the_old_bed(): void
    {
        $nurse = $this->as('enfermeria@nido.test');
        $episode = $this->episodeOf('Hernández Castillo');
        $target = $this->bed('MED-1', '205')->bed_id;

        $this->postJson("/api/v1/internamientos/{$episode}/traslado", ['bed_id' => $target], $nurse)->assertStatus(422)->assertJsonPath('code', 'MOTIVO_REQUERIDO');
        $this->postJson("/api/v1/internamientos/{$episode}/traslado", ['bed_id' => $this->bed('CIR-1', '301')->bed_id, 'reason' => 'Más cerca de enfermería'], $nurse)
            ->assertStatus(409)->assertJsonPath('code', 'CAMA_NO_DISPONIBLE');
        $this->postJson("/api/v1/internamientos/{$episode}/traslado", ['bed_id' => $target, 'reason' => 'Requiere UCI', 'require_icu' => true], $nurse)
            ->assertStatus(409)->assertJsonPath('code', 'CAMA_NO_CUMPLE_REQUISITO');
        $this->postJson("/api/v1/internamientos/{$episode}/traslado", ['bed_id' => $target, 'reason' => 'Más cerca de enfermería'], $nurse)->assertOk();

        $this->assertSame('available', $this->bed('MED-1', '201')->status);
        $this->assertSame('occupied', $this->bed('MED-1', '205')->status);
        $this->assertSame(1, BedStay::query()->where('care_episode_id', $episode)->whereNull('ended_at')->count());
        $this->assertSame(2, BedStay::query()->where('care_episode_id', $episode)->count(), 'El historial conserva la estancia anterior');
    }

    public function test_discharge_closes_the_episode_and_sends_the_bed_to_cleaning(): void
    {
        $doctor = $this->as('medico@nido.test');
        $episode = $this->episodeOf('Xitumul Coc');

        $this->postJson("/api/v1/internamientos/{$episode}/alta", ['type' => 'medica'], $doctor)->assertStatus(422)->assertJsonPath('code', 'MOTIVO_REQUERIDO');
        $this->postJson("/api/v1/internamientos/{$episode}/alta", ['type' => 'medica', 'reason' => 'Evolución favorable, control en 7 días'], $doctor)
            ->assertOk()->assertJsonPath('data.status', 'closed');

        $this->assertSame('maintenance', $this->bed('CIR-1', '301')->status);
        $this->postJson("/api/v1/internamientos/{$episode}/alta", ['type' => 'medica', 'reason' => 'Otra vez'], $doctor)->assertStatus(409)->assertJsonPath('code', 'EPISODIO_NO_DISPONIBLE');
    }

    public function test_death_discharge_marks_the_patient_as_deceased(): void
    {
        $this->postJson('/api/v1/internamientos/'.$this->episodeOf('Morales Díaz').'/alta', ['type' => 'defuncion', 'reason' => 'Paro cardiorrespiratorio'], $this->as('medico@nido.test'))->assertOk();

        $this->assertSame('deceased', LocalPatient::query()->where('family_name', 'Morales Díaz')->value('status'));
    }

    public function test_occupancy_counts_operative_beds_and_flags_inconsistencies(): void
    {
        $headers = $this->as('direccion@nido.test');
        $totals = $this->getJson('/api/v1/ocupacion', $headers)->assertOk()->json('totales');
        // 17 camas: 3 ocupadas, 1 en mantenimiento → 16 operativas.
        $this->assertSame([17, 3, 16, 18.8, 0], [$totals['total'], $totals['occupied'], $totals['operativas'], $totals['ocupacion'], $totals['inconsistentes']]);

        // Una cama marcada «ocupada» sin nadie adentro (defecto AU-05 al revés) se avisa.
        Bed::query()->whereKey($this->bed('UCI', 'U-1')->bed_id)->update(['status' => 'occupied']);
        $uci = collect($this->getJson('/api/v1/ocupacion', $headers)->json('salas'))->firstWhere('code', 'UCI');
        $this->assertSame([0, 1], [$uci['occupied'], $uci['inconsistentes']]);
    }

    public function test_reception_cannot_transfer_and_nursing_cannot_edit_the_catalog(): void
    {
        $this->postJson('/api/v1/internamientos/'.$this->episodeOf('Hernández Castillo').'/traslado', ['bed_id' => $this->bed('MED-1', '205')->bed_id, 'reason' => 'x'],
            $this->as('recepcion@nido.test'))->assertForbidden();
        $this->postJson('/api/v1/salas', ['code' => 'X', 'name' => 'X', 'location' => 'X'], $this->as('enfermeria@nido.test'))->assertForbidden();
        $this->assertSame(4, Ward::query()->count());
    }
}

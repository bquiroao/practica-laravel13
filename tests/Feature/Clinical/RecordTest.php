<?php

namespace Tests\Feature\Clinical;

use App\Events\VitalSignsOutOfRange;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\ClinicalNote;
use App\Models\Hospital\LocalPatient;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;

#[Group('clinical')]
class RecordTest extends ClinicalTestCase
{
    private const SOAP = ['s' => 'Tos y fiebre de 3 días', 'o' => 'T 38.5 °C, crépitos en base derecha', 'a' => 'Neumonía adquirida en la comunidad', 'p' => 'Amoxicilina, control en 48 horas'];

    private function patient(string $family): string
    {
        return LocalPatient::query()->where('family_name', $family)->value('local_patient_id');
    }

    public function test_a_doctor_writes_and_signs_a_soap_note_and_a_consultation_opens_by_itself(): void
    {
        $id = $this->patient('Gómez Ruiz');
        $this->postJson("/api/v1/expediente/{$id}/notas", ['soap' => self::SOAP, 'sign' => true,
            'diagnoses' => [['code' => 'j18.9', 'description' => 'Neumonía, no especificada', 'type' => 'primary']]], $this->as('medico@nido.test'))
            ->assertCreated()->assertJsonPath('data.status', 'signed');

        $record = $this->getJson("/api/v1/expediente/{$id}", $this->as('enfermeria@nido.test'))->assertOk();
        $record->assertJsonPath('record_number', 'EXP-'.LocalPatient::query()->find($id)->code)
            ->assertJsonPath('episodes.0.type', 'outpatient')->assertJsonPath('notes.0.sections.a', 'Neumonía adquirida en la comunidad')
            ->assertJsonPath('diagnoses.0.code', 'J18.9');
    }

    public function test_note_rules_from_the_soap_module(): void
    {
        $id = $this->patient('Gómez Ruiz');
        $doctor = $this->as('medico@nido.test');

        $this->postJson("/api/v1/expediente/{$id}/notas", ['soap' => ['s' => 'x', 'o' => 'y', 'a' => '', 'p' => 'z']], $doctor)
            ->assertStatus(422)->assertJsonPath('code', 'NOTA_INCOMPLETA')->assertJsonPath('details.campo', 'a');
        $this->postJson("/api/v1/expediente/{$id}/notas", ['soap' => self::SOAP, 'diagnoses' => [['code' => 'neumonia', 'description' => 'x', 'type' => 'primary']]], $doctor)
            ->assertStatus(422)->assertJsonPath('code', 'DIAGNOSTICO_INVALIDO');
        $this->postJson("/api/v1/expediente/{$id}/notas", ['soap' => self::SOAP, 'diagnoses' => [
            ['code' => 'J18.9', 'description' => 'a', 'type' => 'primary'], ['code' => 'R50.9', 'description' => 'b', 'type' => 'primary']]], $doctor)
            ->assertStatus(422)->assertJsonPath('code', 'DIAGNOSTICO_INVALIDO');
    }

    public function test_a_draft_can_be_completed_but_a_signed_note_is_corrected_with_a_new_version(): void
    {
        $id = $this->patient('Gómez Ruiz');
        $doctor = $this->as('medico@nido.test');
        $draft = $this->postJson("/api/v1/expediente/{$id}/notas", ['soap' => self::SOAP], $doctor)->assertCreated()->json('data.id');

        $this->putJson("/api/v1/notas/{$draft}", ['soap' => ['p' => 'Plan ajustado'] + self::SOAP, 'sign' => true], $doctor)->assertOk()->assertJsonPath('data.status', 'signed');
        $this->putJson("/api/v1/notas/{$draft}", ['soap' => self::SOAP], $doctor)->assertStatus(409)->assertJsonPath('code', 'NOTA_FIRMADA');
        $this->postJson("/api/v1/notas/{$draft}/correccion", ['soap' => self::SOAP, 'reason' => 'corto'], $doctor)
            ->assertStatus(422)->assertJsonPath('code', 'MOTIVO_CORRECCION_INSUFICIENTE');
        $this->postJson("/api/v1/notas/{$draft}/correccion", ['soap' => ['a' => 'Bronquitis aguda'] + self::SOAP, 'reason' => 'Se descartó neumonía con radiografía'], $doctor)
            ->assertCreated()->assertJsonPath('data.version', 2);
        $this->postJson("/api/v1/notas/{$draft}/correccion", ['soap' => self::SOAP, 'reason' => 'Segunda corrección sobre la vieja'], $doctor)
            ->assertStatus(409)->assertJsonPath('code', 'NOTA_YA_CORREGIDA');

        $notes = $this->getJson("/api/v1/expediente/{$id}", $doctor)->json('notes');
        $this->assertCount(2, $notes);
        $this->assertSame([false, true], [$notes[0]['superseded'], $notes[1]['superseded']]);
    }

    public function test_a_patient_can_have_many_notes(): void
    {
        $id = $this->patient('Gómez Ruiz');
        $doctor = $this->as('medico@nido.test');
        foreach ([1, 2, 3] as $i) {
            $this->postJson("/api/v1/expediente/{$id}/notas", ['soap' => self::SOAP, 'sign' => true], $doctor)->assertCreated();
        }

        $this->assertSame(3, ClinicalNote::query()->count());
    }

    public function test_only_doctors_with_a_profile_write_notes(): void
    {
        $this->postJson('/api/v1/expediente/'.$this->patient('Gómez Ruiz').'/notas', ['soap' => self::SOAP], $this->as('enfermeria@nido.test'))->assertForbidden();
    }

    public function test_nursing_records_vital_signs_and_out_of_range_values_raise_an_event(): void
    {
        Event::fake([VitalSignsOutOfRange::class]);
        $id = $this->patient('Hernández Castillo');

        $response = $this->postJson("/api/v1/expediente/{$id}/signos", ['values' => ['temperature' => 39.2, 'heart_rate' => 88, 'oxygen_saturation' => 86]],
            $this->as('enfermeria@nido.test'))->assertCreated();

        $this->assertSame(['temperature' => 'anormal', 'oxygen_saturation' => 'critica'], collect($response->json('data.findings'))->pluck('nivel', 'signo')->all());
        Event::assertDispatched(VitalSignsOutOfRange::class, fn (VitalSignsOutOfRange $e) => $e->isCritical() && $e->patientId === $id);
        // El paciente está internado: la toma va a su internamiento, no a una consulta nueva.
        $this->assertSame(1, CareEpisode::query()->where('local_patient_id', $id)->count());
    }

    public function test_vital_sign_rules(): void
    {
        $id = $this->patient('Hernández Castillo');
        $nurse = $this->as('enfermeria@nido.test');

        $this->postJson("/api/v1/expediente/{$id}/signos", ['values' => []], $nurse)->assertStatus(422);
        $this->postJson("/api/v1/expediente/{$id}/signos", ['values' => ['heart_rate' => '']], $nurse)->assertStatus(422)->assertJsonPath('code', 'TOMA_VACIA');
        $this->postJson("/api/v1/expediente/{$id}/signos", ['values' => ['temperature' => 50, 'heart_rate' => 80]], $nurse)
            ->assertStatus(422)->assertJsonPath('code', 'MEDICION_INVALIDA')->assertJsonStructure(['details' => ['campos' => ['temperature']]]);
        Event::fake([VitalSignsOutOfRange::class]);
        $this->postJson("/api/v1/expediente/{$id}/signos", ['values' => ['temperature' => 36.8, 'heart_rate' => 72]], $nurse)->assertCreated()->assertJsonPath('data.findings', []);
        Event::assertNotDispatched(VitalSignsOutOfRange::class);
    }

    public function test_outpatient_consultation_can_be_ended_but_an_admission_needs_a_discharge(): void
    {
        $doctor = $this->as('medico@nido.test');
        $episode = $this->postJson('/api/v1/expediente/'.$this->patient('Gómez Ruiz').'/consulta', [], $doctor)->assertCreated()->json('data.id');
        $this->postJson("/api/v1/episodios/{$episode}/terminar", [], $doctor)->assertOk();

        $inpatient = CareEpisode::query()->where('local_patient_id', $this->patient('Hernández Castillo'))->value('care_episode_id');
        $this->postJson("/api/v1/episodios/{$inpatient}/terminar", [], $doctor)->assertStatus(409)->assertJsonPath('code', 'EPISODIO_NO_DISPONIBLE');
    }
}

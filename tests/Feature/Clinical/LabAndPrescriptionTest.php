<?php

namespace Tests\Feature\Clinical;

use App\Events\LabResultCritical;
use App\Models\Central\CentralUser;
use App\Models\Hospital\Allergy;
use App\Models\Hospital\LabOrder;
use App\Models\Hospital\LocalPatient;
use App\Models\Hospital\LocalUserRef;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;

#[Group('clinical')]
class LabAndPrescriptionTest extends ClinicalTestCase
{
    private function patient(string $family): string
    {
        return LocalPatient::query()->where('family_name', $family)->value('local_patient_id');
    }

    private function order(array $tests = ['HB', 'K']): array
    {
        return $this->postJson('/api/v1/expediente/'.$this->patient('Gómez Ruiz').'/laboratorio', ['tests' => $tests, 'indication' => 'Control de anemia', 'priority' => 'urgent'],
            $this->as('medico@nido.test'))->assertCreated()->json('data');
    }

    public function test_order_rules(): void
    {
        $doctor = $this->as('medico@nido.test');
        $url = '/api/v1/expediente/'.$this->patient('Gómez Ruiz').'/laboratorio';

        $this->postJson($url, ['tests' => ['HB', 'HB'], 'indication' => 'x'], $doctor)->assertStatus(422)->assertJsonPath('code', 'PRUEBAS_INVALIDAS');
        $this->postJson($url, ['tests' => ['XYZ'], 'indication' => 'x'], $doctor)->assertStatus(422)->assertJsonPath('code', 'PRUEBAS_INVALIDAS');
        $this->postJson($url, ['tests' => ['HB']], $doctor)->assertStatus(422)->assertJsonPath('code', 'INDICACION_REQUERIDA');
        $this->postJson($url, ['tests' => ['HB'], 'indication' => 'Anemia'], $this->as('enfermeria@nido.test'))->assertForbidden();
        $order = $this->order();
        $this->assertSame(['ordered', 2, 'g/dL'], [$order['status'], count($order['items']), $order['items'][0]['unit']]);
    }

    public function test_full_flow_order_receive_result_validate_and_critical_event(): void
    {
        Event::fake([LabResultCritical::class]);
        $order = $this->order();
        $items = collect($order['items'])->keyBy('test_code');
        $lab = $this->as('laboratorio@nido.test');
        $bio = $this->as('bioquimico@nido.test');

        $received = $this->postJson("/api/v1/laboratorio/items/{$items['HB']['id']}/recibir", [], $lab)->assertOk()->json('data');
        $this->assertMatchesRegularExpression('/^M\d{6}-\d{5}$/', $received['specimen_code']);
        $this->postJson("/api/v1/laboratorio/items/{$items['K']['id']}/rechazar", ['reason' => 'x'], $lab)->assertStatus(422)->assertJsonPath('code', 'MOTIVO_REQUERIDO');
        $this->postJson("/api/v1/laboratorio/items/{$items['K']['id']}/rechazar", ['reason' => 'Muestra hemolizada'], $lab)->assertOk();

        $this->postJson("/api/v1/laboratorio/items/{$items['HB']['id']}/resultado", ['value' => 6.1, 'unit' => 'mg/dL'], $lab)->assertStatus(422)->assertJsonPath('code', 'UNIDAD_DISTINTA');
        $first = $this->postJson("/api/v1/laboratorio/items/{$items['HB']['id']}/resultado", ['value' => 9.5, 'unit' => 'g/dL'], $lab)->assertCreated()->assertJsonPath('data.flag', 'low')->json('data.id');
        $fixed = $this->postJson("/api/v1/laboratorio/items/{$items['HB']['id']}/resultado", ['value' => 6.1, 'unit' => 'g/dL'], $lab)
            ->assertCreated()->assertJsonPath('data.flag', 'critical_low')->assertJsonPath('data.version', 2)->json('data.id');
        $this->postJson("/api/v1/laboratorio/resultados/{$first}/revision", ['approve' => true], $bio)->assertStatus(409)->assertJsonPath('code', 'RESULTADO_YA_REVISADO');

        $this->getJson('/api/v1/laboratorio/trabajo?etapa=validacion', $bio)->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/laboratorio/resultados/{$fixed}/revision", ['approve' => true], $bio)->assertOk()->assertJsonPath('data.status', 'validated');

        $this->assertSame('completed', LabOrder::query()->find($order['id'])->status);
        Event::assertDispatched(LabResultCritical::class, fn ($e) => $e->testCode === 'HB' && $e->flag === 'critical_low');
        $this->getJson('/api/v1/expediente/'.$this->patient('Gómez Ruiz').'/laboratorio', $this->as('medico@nido.test'))->assertOk()
            ->assertJsonPath('data.0.items.0.result.status', 'validated');
    }

    public function test_the_same_person_cannot_validate_what_they_entered_and_rejection_sends_it_back(): void
    {
        $item = $this->order(['GLU'])['items'][0]['id'];
        $bio = $this->as('bioquimico@nido.test');
        $this->postJson("/api/v1/laboratorio/items/{$item}/recibir", [], $bio)->assertOk();
        $result = $this->postJson("/api/v1/laboratorio/items/{$item}/resultado", ['value' => 95, 'unit' => 'mg/dL'], $bio)->json('data.id');

        $this->postJson("/api/v1/laboratorio/resultados/{$result}/revision", ['approve' => true], $bio)->assertForbidden()->assertJsonPath('code', 'MISMA_PERSONA');
        // Otro bioquímico, con el rol asignado por el administrador.
        $admin = $this->as('admin.hospital@nido.test');
        $labUser = CentralUser::query()->where('email', 'laboratorio@nido.test')->value('user_id');
        $this->postJson('/api/v1/membresias', ['user_id' => $labUser, 'role' => 'bioquimico'], $admin)->assertCreated();
        $this->postJson("/api/v1/laboratorio/resultados/{$result}/revision", ['approve' => false, 'note' => 'Repetir, posible error de pipeteo'], $this->as('laboratorio@nido.test'))
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->getJson('/api/v1/laboratorio/trabajo?etapa=resultados', $bio)->assertJsonFragment(['test_code' => 'GLU', 'status' => 'received']);
    }

    public function test_severe_allergy_blocks_and_cross_allergy_is_detected(): void
    {
        $doctor = $this->as('medico@nido.test');
        $url = '/api/v1/expediente/'.$this->patient('Gómez Ruiz').'/recetas';
        $this->seedAllergy('Gómez Ruiz', 'Penicilina', 'severe');

        $this->postJson($url, ['medication_code' => 'AMOX500', 'dose' => '500 mg', 'frequency' => 'Cada 8 horas'], $doctor)
            ->assertStatus(409)->assertJsonPath('code', 'BLOQUEO_ALERGIA_CRUZADA')->assertJsonPath('details.hallazgos.0.allergen', 'Penicilina');
        $this->postJson($url, ['medication_code' => 'PENBEN', 'dose' => '1.2 MUI', 'frequency' => 'Dosis única', 'override_reason' => 'Lo indica infectología'], $doctor)
            ->assertStatus(409)->assertJsonPath('code', 'BLOQUEO_ALERGIA');
        $this->postJson($url, ['medication_code' => 'AZI500', 'dose' => '500 mg', 'frequency' => 'Cada 24 horas', 'duration_days' => 3], $doctor)
            ->assertCreated()->assertJsonPath('data.status', 'issued');
    }

    public function test_mild_allergy_needs_confirmation_with_a_reason(): void
    {
        $doctor = $this->as('medico@nido.test');
        $url = '/api/v1/expediente/'.$this->patient('Gómez Ruiz').'/recetas';
        $this->seedAllergy('Gómez Ruiz', 'Ibuprofeno', 'mild');
        $body = ['medication_code' => 'IBU400', 'dose' => '400 mg', 'frequency' => 'Cada 8 horas'];

        $this->postJson($url, $body, $doctor)->assertStatus(409)->assertJsonPath('code', 'CONFIRMACION_REQUERIDA');
        $this->postJson($url, $body + ['override_reason' => 'Tolera con protector gástrico'], $doctor)->assertCreated()
            ->assertJsonPath('data.allergy_override_reason', 'Tolera con protector gástrico');
    }

    public function test_cancelling_a_prescription_needs_a_reason(): void
    {
        $doctor = $this->as('medico@nido.test');
        $id = $this->postJson('/api/v1/expediente/'.$this->patient('Gómez Ruiz').'/recetas', ['medication_code' => 'PARA500', 'dose' => '500 mg', 'frequency' => 'Cada 6 horas'], $doctor)->json('data.id');

        $this->postJson("/api/v1/recetas/{$id}/anular", ['reason' => 'error'], $doctor)->assertStatus(422)->assertJsonPath('code', 'MOTIVO_REQUERIDO');
        $this->postJson("/api/v1/recetas/{$id}/anular", ['reason' => 'Se cambió por otro analgésico'], $doctor)->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/recetas/{$id}/anular", ['reason' => 'Se cambió por otro analgésico'], $doctor)->assertStatus(409)->assertJsonPath('code', 'YA_ANULADA');
        $this->postJson('/api/v1/expediente/'.$this->patient('Gómez Ruiz').'/recetas', ['medication_code' => 'PARA500', 'dose' => '1', 'frequency' => 'x'], $this->as('enfermeria@nido.test'))->assertForbidden();
    }

    private function seedAllergy(string $family, string $allergen, string $severity): void
    {
        $ref = LocalUserRef::query()->value('local_user_ref_id');
        Allergy::query()->where('local_patient_id', $this->patient($family))->update(['status' => 'resolved', 'resolved_at' => now()->toDateString()]);
        Allergy::query()->create(['local_patient_id' => $this->patient($family), 'verified_by_user_ref_id' => $ref, 'allergen_name' => $allergen,
            'allergy_type' => 'allergy', 'severity' => $severity, 'status' => 'active']);
    }
}

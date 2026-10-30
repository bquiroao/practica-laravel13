<?php

namespace Tests\Feature\Clinical;

use App\Models\Central\AuditLog;
use App\Models\Hospital\LocalPatient;
use PHPUnit\Framework\Attributes\Group;

#[Group('clinical')]
class PatientTest extends ClinicalTestCase
{
    private function payload(array $override = []): array
    {
        return $override + ['given_name' => 'Rosa Elena', 'family_name' => 'Tum Caal', 'birth_date' => '1994-08-15', 'sex' => 'female',
            'document_type' => 'DPI', 'document_number' => '2611 45678 0101', 'contact' => '55123456'];
    }

    public function test_reception_registers_a_patient_with_a_hospital_code(): void
    {
        $response = $this->postJson('/api/v1/pacientes', $this->payload(), $this->as('recepcion@nido.test'))->assertCreated();

        $this->assertMatchesRegularExpression('/^HN-CEN-\d{6}$/', $response->json('data.code'));
        $response->assertJsonPath('data.document_number', '2611456780101')->assertJsonPath('data.version', 1);
        $this->assertTrue(AuditLog::query()->where('accion', 'PACIENTE_REGISTRADO')->exists());
    }

    public function test_invalid_dpi_is_rejected_with_a_clear_message(): void
    {
        $this->postJson('/api/v1/pacientes', $this->payload(['document_number' => '2611456789901']), $this->as('recepcion@nido.test'))
            ->assertStatus(422)->assertJsonPath('code', 'DOCUMENTO_INVALIDO');
    }

    public function test_same_document_blocks_and_tells_the_existing_code(): void
    {
        $headers = $this->as('recepcion@nido.test');
        $code = $this->postJson('/api/v1/pacientes', $this->payload(), $headers)->json('data.code');

        $this->postJson('/api/v1/pacientes', $this->payload(['given_name' => 'Otra', 'family_name' => 'Persona']), $headers)
            ->assertStatus(409)->assertJsonPath('code', 'PACIENTE_EXISTENTE')->assertJsonPath('details.codigo', $code);
    }

    public function test_same_name_and_birth_date_warns_and_needs_a_reason_to_continue(): void
    {
        $headers = $this->as('recepcion@nido.test');
        $this->postJson('/api/v1/pacientes', $this->payload(), $headers)->assertCreated();
        $twin = $this->payload(['given_name' => 'rosa  elena', 'family_name' => 'Túm Caal', 'document_number' => null, 'document_type' => null]);

        $this->postJson('/api/v1/pacientes', $twin, $headers)->assertStatus(409)->assertJsonPath('code', 'POSIBLE_DUPLICADO')
            ->assertJsonCount(1, 'details.candidatos');
        $this->postJson('/api/v1/pacientes', $twin + ['confirmar_duplicado' => true], $headers)->assertStatus(422)->assertJsonPath('code', 'MOTIVO_REQUERIDO');
        $this->postJson('/api/v1/pacientes', $twin + ['confirmar_duplicado' => true, 'motivo' => 'Homónima, distinta madre según DPI físico'], $headers)->assertCreated();
    }

    public function test_search_by_name_ignores_accents_and_case_and_needs_three_letters(): void
    {
        $headers = $this->as('recepcion@nido.test');

        $this->getJson('/api/v1/pacientes?q=ma', $headers)->assertStatus(422)->assertJsonPath('code', 'CRITERIO_INSUFICIENTE');
        $this->getJson('/api/v1/pacientes?q=MARIA perez', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.full_name', 'María José Pérez López');
        $this->getJson('/api/v1/pacientes?q=2456789120101', $headers)->assertOk()->assertJsonPath('data.0.family_name', 'Pérez López');
        $code = LocalPatient::query()->where('family_name', 'Gómez Ruiz')->value('code');
        $this->getJson('/api/v1/pacientes?q='.strtolower($code), $headers)->assertOk()->assertJsonPath('data.0.code', $code);
        $this->getJson('/api/v1/pacientes', $headers)->assertOk()->assertJsonPath('meta.criterio', 'recientes');
    }

    public function test_users_without_manage_permission_see_the_document_masked(): void
    {
        $this->getJson('/api/v1/pacientes?q=2456789120101', $this->as('enfermeria@nido.test'))->assertOk()
            ->assertJsonPath('data.0.document_number', '•••••••••0101');
    }

    public function test_identity_changes_need_a_reason_and_stale_versions_are_rejected(): void
    {
        $headers = $this->as('recepcion@nido.test');
        $patient = $this->postJson('/api/v1/pacientes', $this->payload(), $headers)->json('data');
        $edit = fn (array $changes) => $this->putJson("/api/v1/pacientes/{$patient['id']}", $changes + $this->payload() + ['version' => 1], $headers);

        $edit(['family_name' => 'Tum Cal'])->assertStatus(422)->assertJsonPath('code', 'MOTIVO_REQUERIDO')->assertJsonPath('details.campos', ['family_name']);
        $edit(['contact' => '44001122'])->assertOk()->assertJsonPath('data.version', 2);
        $edit(['contact' => '44001133'])->assertStatus(409)->assertJsonPath('code', 'CONFLICTO_VERSION');
        $this->putJson("/api/v1/pacientes/{$patient['id']}", ['family_name' => 'Tum Cal', 'motivo' => 'Corrección según DPI', 'version' => 2] + $this->payload(), $headers)
            ->assertOk()->assertJsonPath('data.family_name', 'Tum Cal')->assertJsonPath('data.version', 3);
    }

    public function test_nursing_can_read_but_not_register_patients(): void
    {
        $headers = $this->as('enfermeria@nido.test');
        $this->getJson('/api/v1/pacientes?q=perez', $headers)->assertOk();
        $this->postJson('/api/v1/pacientes', $this->payload(), $headers)->assertForbidden();
    }
}

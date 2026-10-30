<?php

namespace Tests\Feature\Platform;

use App\Models\Central\AuditLog;
use App\Models\Central\CentralUser;
use PHPUnit\Framework\Attributes\Group;

#[Group('platform')]
class AccessControlTest extends PlatformTestCase
{
    public function test_every_permission_belongs_to_a_documented_module(): void
    {
        $modules = array_map('basename', glob(base_path('docs/modulos/*'), GLOB_ONLYDIR));
        $used = array_unique(array_column(config('nido.permissions'), 0));

        $this->assertSame([], array_values(array_diff($used, $modules)), 'Permisos con un módulo que no existe');
        $this->assertSame([], array_values(array_diff($modules, $used)), 'Módulos sin ningún permiso en el catálogo');
    }

    public function test_route_without_permission_is_403_and_leaves_an_access_denied_event(): void
    {
        $this->getJson('/api/v1/usuarios', $this->as('medico@nido.test'))->assertForbidden()->assertJsonPath('code', 'PERMISO_NO_ASIGNADO');

        $this->assertTrue(AuditLog::query()->where('accion', 'ACCESO_DENEGADO')->where('detalle->permiso', 'usuarios.ver')->exists());
    }

    public function test_admin_creates_a_user_who_can_log_in_with_the_assigned_role(): void
    {
        $headers = $this->as('admin.hospital@nido.test');

        $this->postJson('/api/v1/usuarios', ['name' => 'Ana Pérez', 'email' => 'ana@nido.test', 'password' => 'ClaveSegura2026', 'role' => 'enfermeria'], $headers)
            ->assertCreated()->assertJsonPath('data.roles.0.role', 'enfermeria');

        $this->login('ana@nido.test', 'HN-CEN', 'ClaveSegura2026')->assertOk()->assertJsonPath('session.roles', ['enfermeria']);
    }

    public function test_user_creation_rejects_weak_password_and_duplicated_email(): void
    {
        $headers = $this->as('admin.hospital@nido.test');

        $this->postJson('/api/v1/usuarios', ['name' => 'X', 'email' => 'x@nido.test', 'password' => 'soloLetrasLargas', 'role' => 'medico'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/usuarios', ['name' => 'X', 'email' => 'MEDICO@nido.test', 'password' => 'ClaveSegura2026', 'role' => 'medico'], $headers)
            ->assertStatus(409)->assertJsonPath('code', 'CORREO_DUPLICADO');
    }

    public function test_assignment_rules_from_the_rbac_module(): void
    {
        $headers = $this->as('admin.hospital@nido.test');
        $self = CentralUser::query()->where('email', 'admin.hospital@nido.test')->value('user_id');
        $nurse = CentralUser::query()->where('email', 'enfermeria@nido.test')->value('user_id');

        $this->postJson('/api/v1/membresias', ['user_id' => $self, 'role' => 'medico'], $headers)->assertStatus(422)->assertJsonPath('code', 'AUTOASIGNACION');
        $this->postJson('/api/v1/membresias', ['user_id' => $nurse, 'role' => 'cirujano'], $headers)->assertStatus(422)->assertJsonPath('code', 'ROL_INVALIDO');
        $this->postJson('/api/v1/membresias', ['user_id' => $nurse, 'role' => 'admin_red'], $headers)->assertForbidden()->assertJsonPath('code', 'ROL_FUERA_DE_ALCANCE');
        $this->postJson('/api/v1/membresias', ['user_id' => $nurse, 'role' => 'enfermeria'], $headers)->assertStatus(409)->assertJsonPath('code', 'ASIGNACION_DUPLICADA');
        $this->postJson('/api/v1/membresias', ['user_id' => $nurse, 'role' => 'farmacia', 'valid_from' => '2026-12-01', 'valid_to' => '2026-11-01'], $headers)
            ->assertStatus(422)->assertJsonPath('code', 'VIGENCIA_INVALIDA');
        $this->postJson('/api/v1/membresias', ['user_id' => $nurse, 'role' => 'farmacia'], $headers)->assertCreated();
    }

    public function test_revocation_needs_a_reason_and_cuts_access_immediately(): void
    {
        $admin = $this->as('admin.hospital@nido.test');
        $nurseHeaders = $this->as('enfermeria@nido.test');
        $membership = CentralUser::query()->where('email', 'enfermeria@nido.test')->firstOrFail()->memberships()->value('membership_id');

        $this->postJson("/api/v1/membresias/{$membership}/revocar", ['motivo' => 'corto'], $admin)->assertStatus(422)->assertJsonPath('code', 'MOTIVO_INSUFICIENTE');
        $this->postJson("/api/v1/membresias/{$membership}/revocar", ['motivo' => 'Traslado a otro hospital'], $admin)->assertOk();

        // El token sigue vigente, pero ya no hay rol en el hospital.
        $this->getJson('/api/v1/auth/me', $nurseHeaders)->assertForbidden();
    }

    public function test_hospital_admin_cannot_remove_or_disable_the_network_admin(): void
    {
        $headers = $this->as('admin.hospital@nido.test');
        $networkAdmin = CentralUser::query()->where('email', 'admin@nido.test')->firstOrFail();
        $membership = $networkAdmin->memberships()->where('hospital_id', $this->hospital()->hospital_id)->value('membership_id');

        $this->postJson("/api/v1/membresias/{$membership}/revocar", ['motivo' => 'Intento de escalar privilegios'], $headers)
            ->assertForbidden()->assertJsonPath('code', 'ROL_FUERA_DE_ALCANCE');
        $this->patchJson("/api/v1/usuarios/{$networkAdmin->user_id}/estado", ['status' => 'inactive'], $headers)
            ->assertForbidden()->assertJsonPath('code', 'ESTADO_FUERA_DE_ALCANCE');

        // Un usuario que solo trabaja en este hospital sí lo puede desactivar, y ya no entra.
        $nurse = CentralUser::query()->where('email', 'enfermeria@nido.test')->value('user_id');
        $this->patchJson("/api/v1/usuarios/{$nurse}/estado", ['status' => 'inactive'], $headers)->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->login('enfermeria@nido.test')->assertUnauthorized();
    }

    public function test_only_network_admin_changes_the_permission_matrix(): void
    {
        $this->putJson('/api/v1/roles/farmacia/permisos', ['permissions' => ['medicamentos.ver']], $this->as('admin.hospital@nido.test'))->assertForbidden();

        $this->putJson('/api/v1/roles/farmacia/permisos', ['permissions' => ['medicamentos.ver', 'dashboard.ver']], $this->as('admin@nido.test'))
            ->assertOk()->assertJsonPath('permissions', ['dashboard.ver', 'medicamentos.ver']);
        $this->putJson('/api/v1/roles/farmacia/permisos', ['permissions' => ['no.existe']], $this->as('admin@nido.test'))
            ->assertStatus(422)->assertJsonPath('code', 'PERMISO_INVALIDO');
    }
}

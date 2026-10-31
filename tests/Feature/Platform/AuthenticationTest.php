<?php

namespace Tests\Feature\Platform;

use App\Models\Central\AuditLog;
use PHPUnit\Framework\Attributes\Group;

#[Group('platform')]
class AuthenticationTest extends PlatformTestCase
{
    public function test_public_hospital_list_shows_the_four_active_hospitals(): void
    {
        $this->getJson('/api/v1/hospitales')->assertOk()->assertJsonCount(4, 'data')->assertJsonFragment(['code' => 'HN-CEN']);
    }

    public function test_login_returns_token_and_session_with_roles_and_permissions(): void
    {
        $this->login('medico@nido.test')->assertOk()
            ->assertJsonPath('session.hospital.code', 'HN-CEN')
            ->assertJsonPath('session.roles', ['medico'])
            ->assertJsonStructure(['access_token', 'expires_in', 'session' => ['user' => ['id', 'name', 'email'], 'permissions']]);
    }

    public function test_blank_fields_never_open_a_session(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => ' ', 'password' => ''], ['X-Tenant-ID' => $this->hospital()->hospital_id])
            ->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_wrong_password_and_missing_role_get_the_same_generic_answer_and_are_audited(): void
    {
        $bad = $this->login('medico@nido.test', 'HN-CEN', 'ContraseñaMala1')->assertStatus(401)->json('message');
        $noRole = $this->login('medico@nido.test', 'HN-ORI')->assertStatus(401)->json('message');

        $this->assertSame($bad, $noRole);
        $this->assertSame(2, AuditLog::query()->where('accion', 'INICIO_SESION')->where('resultado', 'denegado')->count());
    }

    public function test_too_many_attempts_answer_429(): void
    {
        foreach (range(1, 5) as $i) {
            $this->login('medico@nido.test', 'HN-CEN', 'Mala'.$i)->assertStatus(401);
        }

        $this->login('medico@nido.test')->assertStatus(429)->assertJsonPath('code', 'DEMASIADOS_INTENTOS');
    }

    public function test_token_issued_for_one_hospital_is_rejected_in_another(): void
    {
        $headers = $this->as('medico@nido.test');
        $headers['X-Tenant-ID'] = $this->hospital('HN-NOR')->hospital_id;

        $this->getJson('/api/v1/auth/me', $headers)->assertForbidden();
    }

    public function test_switching_hospital_issues_a_new_token_and_invalidates_the_old_one(): void
    {
        $headers = $this->as('medico@nido.test');
        $this->getJson('/api/v1/auth/hospitales', $headers)->assertOk()->assertJsonCount(2, 'data');

        $new = $this->postJson('/api/v1/auth/cambiar-hospital', ['hospital_id' => $this->hospital('HN-NOR')->hospital_id], $headers)
            ->assertOk()->assertJsonPath('session.hospital.code', 'HN-NOR')->json('access_token');

        $this->getJson('/api/v1/auth/me', $headers)->assertUnauthorized();
        $this->getJson('/api/v1/auth/me', ['X-Tenant-ID' => $this->hospital('HN-NOR')->hospital_id, 'Authorization' => 'Bearer '.$new])->assertOk();
        $this->postJson('/api/v1/auth/cambiar-hospital', ['hospital_id' => $this->hospital('HN-ORI')->hospital_id],
            ['X-Tenant-ID' => $this->hospital('HN-NOR')->hospital_id, 'Authorization' => 'Bearer '.$new])->assertForbidden();
    }

    public function test_logout_invalidates_the_token(): void
    {
        $headers = $this->as('medico@nido.test');
        $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();

        $this->getJson('/api/v1/auth/me', $headers)->assertUnauthorized();
    }

    public function test_unknown_api_route_is_404_and_wrong_method_is_405_in_json(): void
    {
        $this->postJson('/api/v1/no-existe')->assertNotFound()->assertJsonPath('message', 'Recurso no encontrado.');
        $this->getJson('/api/v1/auth/login', ['X-Tenant-ID' => $this->hospital()->hospital_id])->assertStatus(405);
    }
}

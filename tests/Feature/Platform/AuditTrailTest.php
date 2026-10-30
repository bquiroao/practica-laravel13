<?php

namespace Tests\Feature\Platform;

use App\Models\Central\AuditLog;
use App\Services\Audit\AuditTrail;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;

#[Group('platform')]
class AuditTrailTest extends PlatformTestCase
{
    public function test_each_hospital_has_its_own_chain_with_consecutive_sequence(): void
    {
        $trail = app(AuditTrail::class);
        $cen = $this->hospital()->hospital_id;
        $nor = $this->hospital('HN-NOR')->hospital_id;

        $trail->record('pruebas', 'A', 'exito', $cen, null);
        $trail->record('pruebas', 'B', 'exito', $nor, null);
        $trail->record('pruebas', 'C', 'exito', $cen, null, null, ['z' => 1, 'a' => ['y' => 2, 'b' => 3]]);

        $this->assertSame([1, 2], AuditLog::query()->where('hospital_id', $cen)->orderBy('id')->pluck('secuencia')->all());
        $this->assertSame(['integra' => true, 'revisados' => 2, 'primera_falla' => null], $trail->verify($cen));
        $this->assertTrue($trail->verify($nor)['integra']);
    }

    public function test_audit_log_is_append_only(): void
    {
        app(AuditTrail::class)->record('pruebas', 'A', 'exito', $this->hospital()->hospital_id, null);

        foreach (['UPDATE audit_logs SET accion = \'X\'', 'DELETE FROM audit_logs', 'TRUNCATE audit_logs'] as $sql) {
            try {
                DB::connection('central')->statement($sql);
                $this->fail("Se permitió: {$sql}");
            } catch (QueryException $e) {
                $this->assertStringContainsString('solo inserción', $e->getMessage());
            }
        }
    }

    public function test_verification_detects_a_tampered_row(): void
    {
        $trail = app(AuditTrail::class);
        $cen = $this->hospital()->hospital_id;
        $trail->record('pruebas', 'A', 'exito', $cen, null);
        $second = $trail->record('pruebas', 'B', 'exito', $cen, null);
        $trail->record('pruebas', 'C', 'exito', $cen, null);

        // Un atacante con acceso al servidor desactiva el trigger y altera una fila.
        $db = DB::connection('central');
        $db->statement('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_sin_update_delete');
        $db->update("UPDATE audit_logs SET accion = 'ALTERADA' WHERE id = ?", [$second->id]);
        $db->statement('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_sin_update_delete');

        $this->assertSame(['integra' => false, 'revisados' => 2, 'primera_falla' => $second->id], $trail->verify($cen));
    }

    public function test_audit_endpoint_filters_by_module_and_result(): void
    {
        $this->login('medico@nido.test', 'HN-CEN', 'Mala12345678');
        $headers = $this->as('admin.hospital@nido.test');

        $this->getJson('/api/v1/auditoria?modulo=autenticacion&resultado=denegado', $headers)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.accion', 'INICIO_SESION');
        $this->getJson('/api/v1/auditoria/verificacion', $headers)->assertOk()->assertJsonPath('integra', true);
    }
}

<?php

namespace Tests\Feature\Clinical;

use App\Models\Central\AuditLog;
use PHPUnit\Framework\Attributes\Group;

#[Group('clinical')]
class ReportsQualitySystemTest extends ClinicalTestCase
{
    public function test_report_preview_validates_filters_and_export_is_audited_with_a_hash(): void
    {
        $headers = $this->as('direccion@nido.test');
        $today = now('America/Guatemala')->toDateString();

        $this->getJson("/api/v1/reportes/operativos?tipo=pacientes&desde={$today}&hasta=2020-01-01", $headers)->assertStatus(422)->assertJsonPath('code', 'RANGO_INVALIDO');
        $this->getJson("/api/v1/reportes/operativos?tipo=inventado&desde={$today}&hasta={$today}", $headers)->assertNotFound();
        $preview = $this->getJson("/api/v1/reportes/operativos?tipo=pacientes&desde={$today}&hasta={$today}", $headers)->assertOk();
        $this->assertSame(10, $preview->json('total'));
        $this->assertSame('Código', $preview->json('columns.1'));

        $export = $this->get("/api/v1/reportes/operativos/exportar?tipo=pacientes&desde={$today}&hasta={$today}", $headers)->assertOk();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $export->getContent());
        $this->assertSame(hash('sha256', $export->getContent()), $export->headers->get('X-Report-Sha256'));
        $this->assertTrue(AuditLog::query()->where('accion', 'REPORTE_EXPORTADO')->where('detalle->sha256', $export->headers->get('X-Report-Sha256'))->exists());
        $this->get("/api/v1/reportes/operativos/exportar?tipo=pacientes&desde={$today}&hasta={$today}", $this->as('medico@nido.test'))->assertForbidden();
    }

    public function test_defect_lifecycle_is_closed_by_qa_after_re_execution(): void
    {
        $qa = $this->as('calidad@nido.test');
        $defect = $this->postJson('/api/v1/calidad/defectos', ['title' => 'La agenda no muestra citas canceladas', 'module' => 'citas-medicas', 'severity' => 'alta',
            'steps' => '1. Cancelar una cita 2. Abrir la agenda', 'version' => '1.0.0'], $qa)->assertCreated()->assertJsonPath('data.code', 'DEF-001')->json('data');

        $this->postJson("/api/v1/calidad/defectos/{$defect['defect_id']}/estado", ['status' => 'cerrado'], $qa)->assertStatus(422)->assertJsonPath('code', 'TRANSICION_INVALIDA');
        $this->postJson("/api/v1/calidad/defectos/{$defect['defect_id']}/estado", ['status' => 'en_correccion'], $qa)->assertOk();
        $this->postJson("/api/v1/calidad/defectos/{$defect['defect_id']}/estado", ['status' => 'corregido', 'note' => 'Se agregó el filtro de estado'], $qa)->assertOk();
        $this->postJson("/api/v1/calidad/defectos/{$defect['defect_id']}/estado", ['status' => 'cerrado'], $qa)->assertStatus(422)->assertJsonPath('code', 'NOTA_REQUERIDA');
        $this->postJson("/api/v1/calidad/defectos/{$defect['defect_id']}/estado", ['status' => 'cerrado', 'note' => 'Re-ejecutado el caso REG-12, pasa'], $qa)
            ->assertOk()->assertJsonPath('data.status', 'cerrado');
    }

    public function test_quality_gate_blocks_on_failures_and_open_critical_defects(): void
    {
        $qa = $this->as('calidad@nido.test');
        $this->postJson('/api/v1/calidad/ejecuciones', ['version' => '1.0.0', 'suite' => 'php artisan test', 'total' => 10, 'passed' => 9, 'failed' => 2], $qa)
            ->assertStatus(422)->assertJsonPath('code', 'CONTEO_INVALIDO');
        $this->postJson('/api/v1/calidad/ejecuciones', ['version' => '1.0.0', 'suite' => 'php artisan test', 'total' => 10, 'passed' => 9, 'failed' => 1], $qa)->assertCreated();
        $this->getJson('/api/v1/calidad/gate/1.0.0', $qa)->assertOk()->assertJsonPath('approved', false);

        $this->postJson('/api/v1/calidad/ejecuciones', ['version' => '1.0.0', 'suite' => 'php artisan test', 'total' => 10, 'passed' => 10, 'failed' => 0], $qa)->assertCreated();
        $this->getJson('/api/v1/calidad/gate/1.0.0', $qa)->assertJsonPath('approved', true);
        $this->postJson('/api/v1/calidad/defectos', ['title' => 'Caída al firmar', 'module' => 'notas-soap-diagnosticos', 'severity' => 'critica', 'steps' => 'Firmar una nota'], $qa);
        $this->getJson('/api/v1/calidad/gate/1.0.0', $qa)->assertJsonPath('approved', false);
    }

    public function test_ci_endpoint_requires_its_token(): void
    {
        config(['nido.ci_token' => 'secreto-ci']);
        $run = ['version' => 'abc123', 'suite' => 'CI', 'total' => 5, 'passed' => 5, 'failed' => 0];

        $this->postJson('/api/v1/calidad/ci/ejecuciones', $run)->assertUnauthorized();
        $this->postJson('/api/v1/calidad/ci/ejecuciones', $run, ['Authorization' => 'Bearer secreto-ci'])->assertCreated()->assertJsonPath('data.source', 'ci');
    }

    public function test_database_check_reports_connections_migrations_and_audit_chain(): void
    {
        $checks = collect($this->getJson('/api/v1/sistema/base-datos', $this->as('admin.hospital@nido.test'))->assertOk()->json('checks'))->keyBy('label');

        $this->assertTrue($checks['Conexión a la base CENTRAL']['ok']);
        $this->assertTrue($checks['Migraciones del hospital']['ok']);
        $this->assertTrue($checks['Bitácora sin alteraciones']['ok']);
        $this->getJson('/api/v1/sistema/base-datos', $this->as('medico@nido.test'))->assertForbidden();
    }

    public function test_openapi_contract_shows_each_role_only_what_it_can_use(): void
    {
        $doctor = $this->getJson('/api/v1/sistema/openapi', $this->as('medico@nido.test'))->assertOk()->json('paths');
        $this->assertArrayHasKey('/expediente/{patient}/notas', $doctor);
        $this->assertArrayNotHasKey('/usuarios', $doctor);

        $full = $this->getJson('/api/v1/sistema/openapi?completo=1', $this->as('calidad@nido.test'))->assertOk();
        $full->assertJsonPath('openapi', '3.0.3');
        $this->assertArrayHasKey('/usuarios', $full->json('paths'));
    }
}

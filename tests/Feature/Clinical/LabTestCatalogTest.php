<?php

namespace Tests\Feature\Clinical;

use App\Models\Central\AuditLog;
use App\Models\Hospital\LabTest;
use App\Models\Hospital\LocalPatient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\Group;

/**
 * Módulo catálogo de pruebas de laboratorio: CU-01 a CU-06, RN-01 a RN-08, criterios CA-01 a CA-10 y defectos D-02 a D-04.
 * El catálogo inicial son las 10 pruebas del catálogo provisional, con su código (HB, GLU…) vigente desde 2026-01-01.
 */
#[Group('clinical')]
class LabTestCatalogTest extends ClinicalTestCase
{
    private const URL = '/api/v1/catalogo/pruebas-laboratorio';

    /** @return array<string, mixed> */
    private function sheet(array $overrides = []): array
    {
        return $overrides + [
            'code' => 'HEM-001', 'name' => 'Hematología completa', 'section' => 'hematologia', 'turnaround_hours' => 4,
            'specimen_type' => 'sangre-total', 'container' => 'Tubo tapa lila (EDTA)', 'min_volume_ml' => 3, 'fasting_hours' => 0,
            'storage' => 'refrigerada', 'stability_hours' => 24, 'unit' => 'g/dL', 'ref_low' => 12, 'ref_high' => 17.5,
            'valid_from' => $this->day(3),
        ];
    }

    private function day(int $offset): string
    {
        return now('America/Guatemala')->addDays($offset)->toDateString();
    }

    private function order(array $codes): TestResponse
    {
        $patient = LocalPatient::query()->orderBy('code')->value('local_patient_id');

        return $this->postJson("/api/v1/expediente/{$patient}/laboratorio", ['tests' => $codes, 'indication' => 'Control', 'priority' => 'routine'], $this->as('medico@nido.test'));
    }

    public function test_the_initial_catalog_is_what_can_be_ordered(): void
    {
        $doctor = $this->as('medico@nido.test');

        $this->getJson(self::URL, $doctor)->assertOk()->assertJsonPath('meta.total', 10);
        $this->getJson(self::URL.'/GLU', $doctor)->assertOk()->assertJsonPath('data.fasting_hours', 8)->assertJsonPath('data.section_label', 'Química clínica')
            ->assertJsonPath('data.specimen_label', 'Suero')->assertJsonPath('data.ref_low', 70)->assertJsonPath('data.initial_load', true);
        // Órdenes lee el catálogo y copia los datos de la prueba al pedirla.
        $this->getJson('/api/v1/laboratorio/pruebas', $doctor)->assertOk()->assertJsonCount(10, 'data')
            ->assertJsonFragment(['code' => 'HB', 'specimen_type' => 'Sangre total · Tubo tapa lila (EDTA)', 'unit' => 'g/dL', 'critical_low' => 7]);
        $this->order(['HB', 'K'])->assertCreated()->assertJsonPath('data.items.0.test_code', 'HB')->assertJsonPath('data.items.0.ref_high', 17.5);
    }

    public function test_registering_a_test_with_its_sample_requirements_and_validity(): void
    {
        $chief = $this->as('bioquimico@nido.test');

        // CA-01: versión 1, vigente desde la fecha indicada; el hospital y el usuario salen de la sesión.
        $this->postJson(self::URL, $this->sheet(['code' => 'hem-001', 'hospital_id' => 'otro']), $chief)->assertCreated()
            ->assertHeader('Location', self::URL.'/HEM-001')->assertJsonPath('data.code', 'HEM-001')->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.valid_from', $this->day(3))->assertJsonPath('data.valid_to', null)->assertJsonPath('data.state', 'programada');
        $this->assertTrue(AuditLog::query()->where('accion', 'PRUEBA_REGISTRADA')->where('recurso', 'prueba:HEM-001')->exists());
        $this->assertNotNull(LabTest::query()->where('code', 'HEM-001')->value('created_by_user_ref_id'), 'RN-08: quién la registró');

        // CA-07 y CA-08: antes de su fecha no aparece, y el detalle responde 404 con su vigencia.
        $this->getJson(self::URL.'?q=hematologia%20completa', $chief)->assertJsonCount(0, 'data');
        $this->getJson(self::URL.'?q=hematologia%20completa&fecha='.$this->day(3), $chief)->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'/HEM-001', $this->as('laboratorio@nido.test'))->assertNotFound()->assertJsonPath('code', 'PRUEBA_NO_VIGENTE')
            ->assertJsonPath('details.vigencia.desde', $this->day(3));
        $this->getJson(self::URL.'/HEM-001?fecha='.$this->day(3), $chief)->assertOk()->assertJsonPath('data.state', 'vigente');
        $this->getJson(self::URL.'/NOP-999', $chief)->assertNotFound()->assertJsonPath('code', 'PRUEBA_NO_ENCONTRADA');
        // Una prueba que todavía no está vigente no se puede ordenar.
        $this->order(['HEM-001'])->assertUnprocessable()->assertJsonPath('code', 'PRUEBAS_INVALIDAS');

        // El código es único: para cambiarla se registra una versión nueva.
        $this->postJson(self::URL, $this->sheet(['name' => 'Otra']), $chief)->assertStatus(409)->assertJsonPath('code', 'CODIGO_EXISTENTE');
    }

    public function test_every_invalid_field_is_rejected_with_422_and_nothing_is_saved(): void
    {
        $chief = $this->as('bioquimico@nido.test');

        // CA-02 y RN-02: código con formato AAA-000.
        $this->postJson(self::URL, $this->sheet(['code' => 'hem1']), $chief)->assertUnprocessable()->assertJsonValidationErrors(['code']);
        // CA-03 y RN-03: un error por cada campo inválido; tipo de muestra fuera de la lista (CN-01).
        $this->postJson(self::URL, $this->sheet(['min_volume_ml' => 0, 'stability_hours' => 0, 'specimen_type' => 'saliva', 'storage' => 'tibia', 'fasting_hours' => -1]), $chief)
            ->assertUnprocessable()->assertJsonValidationErrors(['min_volume_ml', 'stability_hours', 'specimen_type', 'storage', 'fasting_hours']);
        // D-02 y D-03: lo que no cabe en la columna da 422, no 500.
        $this->postJson(self::URL, $this->sheet(['min_volume_ml' => 10000, 'name' => str_repeat('a', 151), 'container' => str_repeat('a', 81),
            'preparation_instructions' => str_repeat('a', 301)]), $chief)->assertUnprocessable()
            ->assertJsonValidationErrors(['min_volume_ml', 'name', 'container', 'preparation_instructions']);
        $this->postJson(self::URL, $this->sheet(['min_volume_ml' => 9999.99]), $chief)->assertCreated();
        // CA-04 y RN-04: la fecha desde no es anterior a hoy y la fecha hasta no es anterior a la desde.
        $this->postJson(self::URL, $this->sheet(['code' => 'QUI-001', 'valid_from' => $this->day(-1)]), $chief)->assertUnprocessable()->assertJsonValidationErrors(['valid_from']);
        $this->postJson(self::URL, $this->sheet(['code' => 'QUI-001', 'valid_from' => $this->day(5), 'valid_to' => $this->day(4)]), $chief)
            ->assertUnprocessable()->assertJsonValidationErrors(['valid_to']);
        $this->postJson(self::URL, $this->sheet(['code' => 'QUI-001', 'valid_from' => $this->day(0), 'valid_to' => $this->day(0)]), $chief)->assertCreated();
        // Los rangos tienen que tener sentido.
        $this->postJson(self::URL, $this->sheet(['code' => 'QUI-002', 'ref_low' => 20, 'ref_high' => 10, 'critical_high' => 15]), $chief)
            ->assertUnprocessable()->assertJsonValidationErrors(['ref_high']);
        $this->postJson(self::URL, $this->sheet(['code' => 'QUI-002', 'critical_low' => 12, 'critical_high' => 17]), $chief)
            ->assertUnprocessable()->assertJsonValidationErrors(['critical_low', 'critical_high']);
        $this->postJson(self::URL, $this->sheet(['code' => 'QUI-002', 'name' => ['x'], 'turnaround_hours' => 'cuatro']), $chief)
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'turnaround_hours']);

        $this->assertSame(12, LabTest::query()->count());
    }

    public function test_a_new_version_closes_the_previous_one_the_day_before(): void
    {
        $chief = $this->as('bioquimico@nido.test');
        $glucose = $this->sheet(['code' => 'GLU-001', 'name' => 'Glucosa en ayunas', 'section' => 'quimica-clinica', 'specimen_type' => 'plasma',
            'container' => 'Tubo tapa gris', 'min_volume_ml' => 2, 'fasting_hours' => 8, 'unit' => 'mg/dL', 'ref_low' => 70, 'ref_high' => 100, 'valid_from' => $this->day(2)]);
        $this->postJson(self::URL, $glucose, $chief)->assertCreated();
        $url = self::URL.'/GLU-001/versiones';

        // CA-06: una versión que empieza el mismo día o antes se superpone y no guarda nada.
        $this->postJson($url, ['min_volume_ml' => 1, 'valid_from' => $this->day(2)] + $glucose, $chief)->assertStatus(409)->assertJsonPath('code', 'VIGENCIA_SUPERPUESTA');
        // CA-05: la versión 2 desde el día 30 cierra la 1 el día 29.
        $this->postJson($url, ['min_volume_ml' => 1, 'valid_from' => $this->day(30), 'version' => 1] + $glucose, $chief)->assertCreated()
            ->assertJsonPath('data.version', 2)->assertJsonPath('data.min_volume_ml', 1);
        $history = $this->getJson($url, $chief)->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertSame([$this->day(29), null], [$history[0]['valid_to'], $history[1]['valid_to']]);
        $this->getJson(self::URL.'/GLU-001?fecha='.$this->day(29), $chief)->assertJsonPath('data.version', 1);
        $this->getJson(self::URL.'/GLU-001?fecha='.$this->day(30), $chief)->assertJsonPath('data.version', 2);
        $this->assertTrue(AuditLog::query()->where('accion', 'PRUEBA_NUEVA_VERSION')->where('detalle->anterior_hasta', $this->day(29))->exists());
        // Quien seguía viendo la versión 1 no la pisa.
        $this->postJson($url, ['valid_from' => $this->day(40), 'version' => 1] + $glucose, $chief)->assertStatus(409)->assertJsonPath('code', 'CONFLICTO_CONCURRENTE');

        // Una prueba inicial también se versiona; hoy sigue vigente la 1 (el último día está incluido) y avisa que vence.
        $hb = $this->getJson(self::URL.'/HB', $chief)->json('data');
        $this->postJson(self::URL.'/HB/versiones', ['ref_low' => 12.5, 'valid_from' => $this->day(1)] + $hb, $chief)->assertCreated()->assertJsonPath('data.code', 'HB');
        $this->getJson(self::URL.'/HB', $chief)->assertJsonPath('data.version', 1)->assertJsonPath('data.valid_to', $this->day(0))
            ->assertJsonPath('data.expiring', true)->assertJsonPath('data.expires_in_days', 0);
        $this->order(['HB'])->assertCreated()->assertJsonPath('data.items.0.ref_low', 12);

        // RN-08: una versión no se edita ni se borra, ni siquiera saltándose el servicio.
        $this->expectException(QueryException::class);
        DB::connection('hospital')->table('lab_tests')->where('code', 'GLU-001')->where('version', 1)->update(['name' => 'Otro nombre']);
    }

    public function test_a_version_is_never_deleted(): void
    {
        $this->expectException(QueryException::class);
        DB::connection('hospital')->table('lab_tests')->where('code', 'HB')->delete();
    }

    public function test_a_database_failure_saves_nothing(): void
    {
        $chief = $this->as('bioquimico@nido.test');
        $hb = $this->getJson(self::URL.'/HB', $chief)->json('data');
        // CA-10 y RN-07: la base falla al guardar la versión 2, después de cerrar la 1 en la misma transacción.
        DB::connection('hospital')->unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fail_lab_test_insert() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'falla simulada'; END; $$;
            CREATE TRIGGER fail_lab_test_insert BEFORE INSERT ON lab_tests FOR EACH ROW EXECUTE FUNCTION fail_lab_test_insert();
            SQL);
        try {
            $response = $this->postJson(self::URL.'/HB/versiones', ['ref_low' => 12.5, 'valid_from' => $this->day(5)] + $hb, $chief)
                ->assertStatus(500)->assertJsonPath('code', 'ERROR_PERSISTENCIA')->assertHeader('X-Request-Id');
        } finally {
            // migrate:fresh borra las tablas, no las funciones sueltas.
            DB::connection('hospital')->unprepared('DROP TRIGGER fail_lab_test_insert ON lab_tests; DROP FUNCTION fail_lab_test_insert();');
        }
        $this->assertStringNotContainsString('falla simulada', $response->getContent());
        $this->assertNull(LabTest::query()->where('code', 'HB')->value('valid_to'), 'La versión 1 conserva su vigencia original');
        $this->assertSame(1, LabTest::query()->where('code', 'HB')->count());
    }

    public function test_search_filters_by_text_section_and_specimen_with_literal_wildcards(): void
    {
        $doctor = $this->as('medico@nido.test');

        $this->getJson(self::URL.'?q=glucosa', $doctor)->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'GLU');
        $this->getJson(self::URL.'?q=nitrogeno', $doctor)->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'UREA');
        $this->getJson(self::URL.'?seccion=hematologia', $doctor)->assertJsonCount(3, 'data');
        $this->getJson(self::URL.'?muestra=suero', $doctor)->assertJsonPath('meta.total', 6);
        $this->getJson(self::URL.'?q=quimica&muestra=sangre-total', $doctor)->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'HBA1C');
        // D-04: % y _ son texto, no comodines.
        $this->getJson(self::URL.'?q=b_n', $doctor)->assertJsonCount(0, 'data');
        $this->getJson(self::URL.'?q=%25%25', $doctor)->assertJsonCount(0, 'data');
        $this->getJson(self::URL.'?q=a&seccion=cardiologia&fecha=2027-02-30', $doctor)->assertUnprocessable()->assertJsonValidationErrors(['q', 'seccion', 'fecha']);
    }

    public function test_only_the_lab_chief_and_the_hospital_administrator_register_tests(): void
    {
        // CA-09 y RN-01: médico y laboratorista consultan pero no registran.
        foreach (['medico@nido.test', 'laboratorio@nido.test'] as $email) {
            $headers = $this->as($email);
            $this->getJson(self::URL.'?q=glucosa', $headers)->assertOk();
            $this->postJson(self::URL, $this->sheet(), $headers)->assertForbidden();
            $this->postJson(self::URL.'/HB/versiones', $this->sheet(), $headers)->assertForbidden();
        }
        foreach (['recepcion@nido.test', 'farmacia@nido.test'] as $email) {
            $this->getJson(self::URL, $this->as($email))->assertForbidden();
        }
        $this->getJson(self::URL, ['X-Tenant-ID' => $this->hospital()->hospital_id])->assertUnauthorized();
        $this->postJson(self::URL, $this->sheet(), $this->as('admin.hospital@nido.test'))->assertCreated();
    }

    public function test_each_request_is_logged_with_its_request_id_and_without_clinical_data(): void
    {
        Log::spy();
        $this->getJson(self::URL.'/opciones', $this->as('medico@nido.test') + ['X-Request-Id' => 'evidencia-lab-01'])->assertOk()
            ->assertHeader('X-Request-Id', 'evidencia-lab-01')->assertJsonPath('data.sections.0', ['value' => 'hematologia', 'label' => 'Hematología']);

        // RNF-06: identificador, ruta y estado.
        Log::shouldHaveReceived('info')->with('catalogo-lab', Mockery::on(fn ($c) => $c === ['request_id' => 'evidencia-lab-01',
            'ruta' => 'GET api/v1/catalogo/pruebas-laboratorio/opciones', 'estado' => 200]));
    }

    public function test_the_tests_by_lab_section_indicator_uses_the_catalog(): void
    {
        $this->order(['HB', 'K', 'GLU'])->assertCreated();
        $range = '?desde='.$this->day(0).'&hasta='.$this->day(0);
        $director = $this->as('direccion@nido.test');

        $this->getJson('/api/v1/reportes/pruebas_seccion'.$range, $director)->assertOk()->assertJsonPath('datos.indicador.numerador', 3);
        $parts = $this->getJson('/api/v1/reportes/pruebas_seccion/desglose'.$range.'&dimension=seccion', $director)->assertOk()
            ->assertJsonPath('datos.desglose.verificado', true)->collect('datos.desglose.partes')->pluck('valor', 'parte');
        $this->assertSame(['Hematología' => 1, 'Química clínica' => 2], $parts->sortKeys()->all());
    }
}

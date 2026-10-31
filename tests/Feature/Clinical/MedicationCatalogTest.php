<?php

namespace Tests\Feature\Clinical;

use App\Models\Central\AuditLog;
use App\Models\Hospital\LocalPatient;
use App\Models\Hospital\Medication;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;

/**
 * Módulo catálogo de medicamentos: CU-01 a CU-06, RN-01 a RN-06, criterios CA-01 a CA-13 y defectos D-01 a D-03.
 * El catálogo inicial son los 13 medicamentos del catálogo provisional: MED-000001 es Amoxicilina 500 mg (AMOX500),
 * MED-000007 Acetaminofén 500 mg (PARA500), MED-000008 Ibuprofeno 400 mg (IBU400) y MED-000010 Omeprazol 20 mg (OMEP20).
 */
#[Group('clinical')]
class MedicationCatalogTest extends ClinicalTestCase
{
    private const URL = '/api/v1/catalogo/medicamentos';

    /** @return array<string, mixed> */
    private function sheet(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Loratadina 10 mg tableta', 'active_ingredient' => 'Loratadina', 'strength_value' => '10', 'strength_unit' => 'mg',
            'dosage_form' => 'TABLETA', 'route' => 'ORAL', 'presentation' => 'Caja con 10 tabletas', 'sanitary_registration' => 'rs-fic-0107',
            'therapeutic_group' => 'OTRO',
        ];
    }

    private function day(int $offset): string
    {
        return now('America/Guatemala')->addDays($offset)->toDateString();
    }

    private function prescribe(string $code, array $headers): TestResponse
    {
        $patient = LocalPatient::query()->orderBy('code')->get()[1]; // Gómez Ruiz: solo Ibuprofeno leve

        return $this->postJson("/api/v1/expediente/{$patient->local_patient_id}/recetas", ['medication_code' => $code, 'dose' => '500 mg', 'frequency' => 'Cada 8 horas'], $headers);
    }

    public function test_the_initial_catalog_is_what_prescriptions_can_prescribe(): void
    {
        $doctor = $this->as('medico@nido.test');

        $this->getJson(self::URL, $doctor)->assertOk()->assertJsonPath('meta.total', 13)->assertJsonPath('meta.limite', 50);
        $this->getJson(self::URL.'?q=amoxi', $doctor)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'MED-000001')->assertJsonPath('data.0.legacy_code', 'AMOX500')->assertJsonPath('data.0.strength', '500 mg')
            ->assertJsonPath('data.0.allergy_groups', ['penicilinas'])->assertJsonPath('data.0.state', 'vigente');
        // La lista de prescripciones sale del catálogo, con las familias para las alergias cruzadas.
        $this->getJson('/api/v1/medicamentos', $doctor)->assertOk()->assertJsonCount(13, 'data')
            ->assertJsonFragment(['code' => 'MED-000001', 'name' => 'Amoxicilina 500 mg cápsula', 'route' => 'oral', 'groups' => ['penicilinas']]);
        // Se receta por el código nuevo y, para lo anterior, también por el provisional.
        $this->prescribe('MED-000007', $doctor)->assertCreated()->assertJsonPath('data.medication_code', 'MED-000007');
        $this->prescribe('para500', $doctor)->assertCreated()->assertJsonPath('data.medication_name', 'Acetaminofén 500 mg tableta');
    }

    public function test_registering_assigns_the_next_code_and_reports_every_invalid_field_at_once(): void
    {
        $pharmacy = $this->as('farmacia@nido.test');

        // CA-01 y RN-06: siguiente código libre, versión 1, vigencia abierta; el hospital y el usuario no se reciben.
        $this->postJson(self::URL, $this->sheet(['hospital_id' => 'otro', 'code' => 'MED-999999']), $pharmacy)->assertCreated()
            ->assertHeader('Location', '/api/v1/catalogo/medicamentos/MED-000014')->assertHeader('X-Request-Id')
            ->assertJsonPath('data.code', 'MED-000014')->assertJsonPath('data.version', 1)->assertJsonPath('data.valid_to', null)
            ->assertJsonPath('data.valid_from', $this->day(0))->assertJsonPath('data.sanitary_registration', 'RS-FIC-0107')
            ->assertJsonPath('data.allergy_groups', []);
        $this->assertTrue(AuditLog::query()->where('accion', 'MEDICAMENTO_REGISTRADO')->where('recurso', 'medicamento:MED-000014')->exists());

        // CA-02, CA-03 y RN-05: límites de las columnas, todos los errores en una sola respuesta.
        $this->postJson(self::URL, $this->sheet(['name' => str_repeat('a', 151)]), $pharmacy)->assertUnprocessable()->assertJsonValidationErrors(['name']);
        $this->postJson(self::URL, $this->sheet(['strength_value' => '1000000']), $pharmacy)->assertUnprocessable()->assertJsonValidationErrors(['strength_value']);
        $this->postJson(self::URL, $this->sheet(['strength_value' => '1.2345']), $pharmacy)->assertUnprocessable()->assertJsonValidationErrors(['strength_value']);
        // D-01: concentración «0» sin unidad informa los dos errores a la vez.
        $this->postJson(self::URL, $this->sheet(['strength_value' => '0', 'strength_unit' => '']), $pharmacy)->assertUnprocessable()
            ->assertJsonValidationErrors(['strength_value', 'strength_unit']);
        $this->postJson(self::URL, $this->sheet(['dosage_form' => 'GRAGEA', 'route' => 'NASAL', 'therapeutic_group' => 'X', 'allergy_groups' => ['inventada'],
            'sanitary_registration' => 'R$', 'valid_from' => '1999-12-31', 'presentation' => 'ab']), $pharmacy)->assertUnprocessable()
            ->assertJsonValidationErrors(['dosage_form', 'route', 'therapeutic_group', 'allergy_groups.0', 'sanitary_registration', 'valid_from', 'presentation']);
        $this->postJson(self::URL, $this->sheet(['name' => ['Loratadina'], 'active_ingredient' => 12345]), $pharmacy)->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'active_ingredient']);
        $this->assertSame(14, Medication::query()->count(), 'Un alta rechazada no guarda nada');

        // Las familias de alergia se sugieren por el principio activo si no se indican.
        $this->postJson(self::URL, $this->sheet(['name' => 'Cefalexina 500 mg cápsula', 'active_ingredient' => 'Cefalexina', 'strength_value' => 500,
            'dosage_form' => 'CAPSULA', 'therapeutic_group' => 'ANTIBIOTICO']), $pharmacy)->assertCreated()
            ->assertJsonPath('data.code', 'MED-000015')->assertJsonPath('data.allergy_groups', ['cefalosporinas']);
    }

    public function test_an_equivalent_medication_is_a_duplicate_only_while_both_are_valid(): void
    {
        $pharmacy = $this->as('farmacia@nido.test');
        $omeprazole = $this->sheet(['name' => 'Omeprazol 20 mg cápsula genérico', 'active_ingredient' => 'Omeprazol', 'strength_value' => '20.000',
            'dosage_form' => 'CAPSULA', 'presentation' => 'Frasco con 30 cápsulas', 'therapeutic_group' => 'GASTROINTESTINAL']);

        // CA-04 y RN-04: misma clave aunque cambien mayúsculas, tildes, espacios o ceros de la concentración.
        $this->postJson(self::URL, $this->sheet(['name' => 'Amoxicilina genérica', 'active_ingredient' => '  amoxicilína ', 'strength_value' => '500.0',
            'dosage_form' => 'CAPSULA', 'therapeutic_group' => 'ANTIBIOTICO']), $pharmacy)
            ->assertStatus(409)->assertJsonPath('code', 'DUPLICADO')->assertJsonPath('details.codigo_existente', 'MED-000001');
        $this->postJson(self::URL, $omeprazole, $pharmacy)->assertStatus(409)->assertJsonPath('details.codigo_existente', 'MED-000010');

        // CA-05: si el equivalente se da de baja, otro puede empezar desde ese día porque las vigencias no se cruzan.
        $this->postJson(self::URL.'/MED-000010/baja', ['date' => $this->day(5), 'reason' => 'Cambio de proveedor'], $pharmacy)->assertOk();
        $this->postJson(self::URL, $omeprazole + ['valid_from' => $this->day(4)], $pharmacy)->assertStatus(409)->assertJsonPath('code', 'DUPLICADO');
        $this->postJson(self::URL, $omeprazole + ['valid_from' => $this->day(5)], $pharmacy)->assertCreated()->assertJsonPath('data.state', 'programada');

        // RNF-04: la base repite la regla aunque alguien se salte el servicio.
        $this->expectException(QueryException::class);
        $row = Medication::query()->where('code', 'MED-000001')->firstOrFail()->only(['active_ingredient', 'strength_value', 'strength_unit', 'dosage_form', 'route',
            'presentation', 'therapeutic_group', 'equivalence_key']);
        Medication::query()->create($row + ['code' => 'MED-000099', 'version' => 1, 'name' => 'Copia', 'search_text' => 'copia', 'valid_from' => $this->day(0), 'created_at' => now()]);
    }

    public function test_search_returns_only_versions_valid_on_the_date_and_treats_wildcards_as_text(): void
    {
        $pharmacy = $this->as('farmacia@nido.test');
        $doctor = $this->as('medico@nido.test');
        $ibuprofen = $this->getJson(self::URL.'/MED-000008', $pharmacy)->assertOk()->json('data');

        // CA-07: con una versión 2 desde dentro de 10 días, cada fecha ve una sola versión.
        $this->postJson(self::URL.'/MED-000008/versiones', ['presentation' => 'Caja con 20 tabletas', 'valid_from' => $this->day(10), 'version' => 1] + $ibuprofen, $pharmacy)
            ->assertCreated()->assertJsonPath('data.version', 2);
        $this->getJson(self::URL.'?q=ibupro', $doctor)->assertJsonCount(1, 'data')->assertJsonPath('data.0.version', 1);
        $this->getJson(self::URL.'?q=ibupro&fecha='.$this->day(9), $doctor)->assertJsonPath('data.0.version', 1);
        $this->getJson(self::URL.'?q=ibupro&fecha='.$this->day(10), $doctor)->assertJsonCount(1, 'data')->assertJsonPath('data.0.version', 2)
            ->assertJsonPath('data.0.presentation', 'Caja con 20 tabletas');

        // CA-06: dado de baja, ya no aparece desde la fecha de la baja.
        $this->postJson(self::URL.'/MED-000010/baja', ['date' => $this->day(1), 'reason' => 'Retiro del formulario'], $pharmacy)->assertOk();
        $this->getJson(self::URL.'?q=omepra', $doctor)->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?q=omepra&fecha='.$this->day(1), $doctor)->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);

        // CA-08 y RN-03: % y _ se buscan como texto, no como comodines; la búsqueda no distingue tildes.
        $this->postJson(self::URL, $this->sheet(['name' => 'Cloruro de sodio 0,9 % solución inyectable', 'active_ingredient' => 'Cloruro de sodio', 'strength_value' => '0.9',
            'strength_unit' => '%', 'dosage_form' => 'SOLUCION_INYECTABLE', 'route' => 'INTRAVENOSA', 'presentation' => 'Bolsa 500 mL', 'therapeutic_group' => 'SOLUCIONES']), $pharmacy)->assertCreated();
        $this->postJson(self::URL, $this->sheet(['name' => 'Amoxicilina 250 mg/5 mL suspensión', 'active_ingredient' => 'Amoxicilina', 'strength_value' => '250',
            'strength_unit' => 'mg/5mL', 'dosage_form' => 'SUSPENSION', 'presentation' => 'Frasco 100 mL', 'therapeutic_group' => 'ANTIBIOTICO']), $pharmacy)->assertCreated();
        $this->getJson(self::URL.'?q='.urlencode('% s'), $doctor)->assertJsonCount(1, 'data')->assertJsonPath('data.0.active_ingredient', 'Cloruro de sodio');
        $this->getJson(self::URL.'?q=a_o', $doctor)->assertJsonCount(0, 'data');
        $this->getJson(self::URL.'?q=solucion', $doctor)->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?forma=SUSPENSION', $doctor)->assertJsonCount(1, 'data')->assertJsonPath('data.0.strength', '250 mg/5mL');
        $this->getJson(self::URL.'?q=a&forma=GRAGEA', $doctor)->assertUnprocessable()->assertJsonValidationErrors(['q', 'forma']);
    }

    public function test_a_new_version_closes_the_previous_one_without_overlapping(): void
    {
        $pharmacy = $this->as('farmacia@nido.test');
        $current = $this->getJson(self::URL.'/MED-000001', $pharmacy)->assertOk()->assertJsonPath('meta.vigente', true)->json('data');
        $url = self::URL.'/MED-000001/versiones';

        // CA-10 y RN-01: la versión nueva debe empezar después de la vigente.
        foreach (['2025-12-31', '2026-01-01'] as $from) {
            $this->postJson($url, ['presentation' => 'Caja con 21 cápsulas', 'valid_from' => $from] + $current, $pharmacy)->assertStatus(409)->assertJsonPath('code', 'SOLAPE_VIGENCIA');
        }
        $this->postJson($url, ['valid_from' => $this->day(30)] + $current, $pharmacy)->assertUnprocessable()->assertJsonPath('code', 'SIN_CAMBIOS');
        $this->postJson($url, ['presentation' => 'Caja con 21 cápsulas', 'valid_from' => $this->day(30), 'version' => 7] + $current, $pharmacy)
            ->assertStatus(409)->assertJsonPath('code', 'CONFLICTO_CONCURRENTE')->assertJsonPath('details.version_actual', 1);

        // CA-09: la versión 1 queda hasta el inicio de la 2; el código y el código provisional se conservan.
        $this->postJson($url, ['presentation' => 'Caja con 21 cápsulas', 'valid_from' => $this->day(30), 'version' => 1] + $current, $pharmacy)->assertCreated()
            ->assertJsonPath('data.code', 'MED-000001')->assertJsonPath('data.version', 2)->assertJsonPath('data.legacy_code', 'AMOX500')->assertJsonPath('data.state', 'programada');
        $history = $this->getJson($url, $this->as('calidad@nido.test'))->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertSame([$this->day(30), null], [$history[0]['valid_to'], $history[1]['valid_to']]);
        $this->assertSame(['vigente', 'programada'], array_column($history, 'state'));
        $this->assertSame('Carga inicial', $history[0]['created_by']);
        $this->assertSame(['presentation'], AuditLog::query()->where('accion', 'MEDICAMENTO_NUEVA_VERSION')->firstOrFail()->detalle['cambios']);
        // Quien estaba viendo la versión 1 ya no puede cambiarla: otra persona la cerró.
        $this->postJson($url, ['presentation' => 'Caja con 14 cápsulas', 'valid_from' => $this->day(40), 'version' => 1] + $current, $pharmacy)
            ->assertStatus(409)->assertJsonPath('code', 'CONFLICTO_CONCURRENTE');
        // Hasta esa fecha se sigue recetando la versión 1.
        $this->assertSame('Cápsula 500 mg', $this->getJson('/api/v1/medicamentos', $this->as('medico@nido.test'))->collect('data')->firstWhere('code', 'MED-000001')['presentation']);

        // Una versión no se edita: la base lo impide aunque alguien se salte el servicio.
        $this->expectException(QueryException::class);
        DB::connection('hospital')->table('medications')->where('code', 'MED-000001')->where('version', 1)->update(['name' => 'Otro nombre']);
    }

    public function test_a_medication_used_in_prescriptions_is_retired_and_never_deleted(): void
    {
        $pharmacy = $this->as('farmacia@nido.test');
        $doctor = $this->as('medico@nido.test');
        $this->prescribe('MED-000007', $doctor)->assertCreated();

        // CA-11 y RN-02: usado en una receta, no se elimina; se sugiere la baja.
        $this->deleteJson(self::URL.'/MED-000007', [], $pharmacy)->assertStatus(409)->assertJsonPath('code', 'EN_USO')
            ->assertJsonPath('details.accion_sugerida', 'baja')->assertJsonPath('details.recetas', 1);
        $this->getJson(self::URL.'/MED-000007', $pharmacy)->assertJsonPath('meta.puede_eliminarse', false)->assertJsonPath('meta.recetas', 1);

        // CU-04: la baja necesita motivo y no puede ser con fecha pasada.
        $this->postJson(self::URL.'/MED-000007/baja', ['reason' => 'no'], $pharmacy)->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->postJson(self::URL.'/MED-000007/baja', ['date' => $this->day(-1), 'reason' => 'Retiro del formulario'], $pharmacy)
            ->assertUnprocessable()->assertJsonPath('code', 'FECHA_BAJA_INVALIDA');
        $this->postJson(self::URL.'/MED-000007/baja', ['reason' => 'Retiro del formulario', 'version' => 1], $pharmacy)->assertOk()
            ->assertJsonPath('data.valid_to', $this->day(0))->assertJsonPath('data.state', 'baja')->assertJsonPath('data.close_reason', 'Retiro del formulario');
        $this->assertTrue(AuditLog::query()->where('accion', 'MEDICAMENTO_DADO_DE_BAJA')->where('detalle->motivo', 'Retiro del formulario')->exists());

        // Dado de baja: ya no se receta, y no admite otra baja ni versiones nuevas. El registro se conserva.
        $this->prescribe('PARA500', $doctor)->assertUnprocessable()->assertJsonPath('code', 'MEDICAMENTO_NO_ENCONTRADO');
        $this->postJson(self::URL.'/MED-000007/baja', ['reason' => 'Retiro del formulario'], $pharmacy)->assertStatus(409)->assertJsonPath('code', 'YA_DADO_DE_BAJA');
        $this->getJson(self::URL.'/MED-000007', $pharmacy)->assertOk()->assertJsonPath('meta.vigente', false)->assertJsonPath('meta.abierto', false);

        // CU-06: un registro erróneo que nunca se usó sí se elimina, con todas sus versiones.
        $this->postJson(self::URL, $this->sheet(), $pharmacy)->assertCreated();
        $this->deleteJson(self::URL.'/MED-000014', [], $pharmacy)->assertNoContent();
        $this->getJson(self::URL.'/MED-000014', $pharmacy)->assertNotFound()->assertJsonPath('code', 'MEDICAMENTO_NO_ENCONTRADO');
        $this->deleteJson(self::URL.'/MED-000014', [], $pharmacy)->assertNotFound();
        $this->assertTrue(AuditLog::query()->where('accion', 'MEDICAMENTO_ELIMINADO')->exists());

        // La base tampoco deja borrar lo que se recetó.
        $this->expectException(QueryException::class);
        DB::connection('hospital')->table('medications')->where('code', 'MED-000007')->delete();
    }

    public function test_only_pharmacy_and_the_hospital_administrator_change_the_catalog(): void
    {
        $sheet = $this->sheet();
        // CA-12 y RNF-01: médico y enfermería consultan; calidad revisa el historial; nadie de ellos modifica.
        foreach (['medico@nido.test', 'enfermeria@nido.test', 'calidad@nido.test'] as $email) {
            $headers = $this->as($email);
            $this->getJson(self::URL.'?q=amoxi', $headers)->assertOk();
            $this->postJson(self::URL, $sheet, $headers)->assertForbidden();
            $this->postJson(self::URL.'/MED-000001/baja', ['reason' => 'Retiro del formulario'], $headers)->assertForbidden();
            $this->deleteJson(self::URL.'/MED-000001', [], $headers)->assertForbidden();
        }
        $this->getJson(self::URL.'/MED-000001/versiones', $this->as('calidad@nido.test'))->assertOk();
        foreach (['recepcion@nido.test', 'laboratorio@nido.test'] as $email) {
            $this->getJson(self::URL, $this->as($email))->assertForbidden();
        }
        $this->getJson(self::URL, ['X-Tenant-ID' => $this->hospital()->hospital_id])->assertUnauthorized();
        $this->postJson(self::URL, $sheet, $this->as('admin.hospital@nido.test'))->assertCreated();
    }

    public function test_every_response_carries_a_request_id_and_an_unavailable_database_answers_503(): void
    {
        $pharmacy = $this->as('farmacia@nido.test');

        // RNF-05: se respeta el X-Request-Id del cliente, también en los errores.
        $this->getJson(self::URL.'/opciones', $pharmacy + ['X-Request-Id' => 'evidencia-0005'])->assertOk()->assertHeader('X-Request-Id', 'evidencia-0005')
            ->assertJsonPath('data.forms.0', ['value' => 'TABLETA', 'label' => 'Tableta'])->assertJsonPath('data.allergy_groups.0.value', 'penicilinas');
        $this->postJson(self::URL, [], $pharmacy + ['X-Request-Id' => 'evidencia-0006'])->assertUnprocessable()->assertHeader('X-Request-Id', 'evidencia-0006');
        $this->getJson(self::URL.'/MED-123456', $pharmacy)->assertNotFound()->assertHeader('X-Request-Id');

        // CA-13 y D-02: con la base del hospital caída, 503 PERSISTENCIA con X-Request-Id y sin detalles internos.
        $port = config('database.connections.hospital.port');
        config(['database.connections.hospital.port' => 1]);
        DB::purge('hospital');
        try {
            $response = $this->postJson(self::URL, $this->sheet(), $pharmacy + ['X-Request-Id' => 'evidencia-0013'])->assertStatus(503)
                ->assertHeader('Retry-After', '5')->assertHeader('X-Request-Id', 'evidencia-0013')
                ->assertJsonPath('code', 'PERSISTENCIA')->assertJsonPath('details.request_id', 'evidencia-0013');
            $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        } finally {
            config(['database.connections.hospital.port' => $port]);
            DB::purge('hospital');
        }
        $this->assertSame(13, Medication::query()->count(), 'Sin datos parciales');
    }

    public function test_the_prescriptions_by_therapeutic_group_indicator_uses_the_catalog(): void
    {
        $doctor = $this->as('medico@nido.test');
        $this->prescribe('MED-000007', $doctor)->assertCreated();
        $this->prescribe('PARA500', $doctor)->assertCreated();
        $this->prescribe('AZI500', $doctor)->assertCreated();
        $range = '?desde='.$this->day(0).'&hasta='.$this->day(0);
        $director = $this->as('direccion@nido.test');

        $this->getJson('/api/v1/reportes/recetas_grupo'.$range, $director)->assertOk()->assertJsonPath('datos.indicador.numerador', 3);
        $parts = $this->getJson('/api/v1/reportes/recetas_grupo/desglose'.$range.'&dimension=grupo', $director)->assertOk()
            ->assertJsonPath('datos.desglose.verificado', true)->collect('datos.desglose.partes')->pluck('valor', 'parte');
        $this->assertSame(['Analgésicos y antiinflamatorios' => 2, 'Antibióticos' => 1], $parts->sortKeys()->all());
    }
}

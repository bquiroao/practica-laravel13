<?php

namespace Tests\Feature\Clinical;

use App\Models\Hospital\Appointment;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\LocalPatient;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\IndicatorRegistry;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;

/** Módulo reportes y analytics básicos: los tres niveles, sus reglas (RN-01 a RN-09) y el contrato común (RNF-06). */
#[Group('clinical')]
class AnalyticsTest extends ClinicalTestCase
{
    private function today(): string
    {
        return CarbonImmutable::now('America/Guatemala')->toDateString();
    }

    public function test_occupancy_is_traceable_from_the_value_down_to_its_records(): void
    {
        $headers = $this->as('direccion@nido.test');
        $today = $this->today();

        // Demostración: 3 internados con cama hoy, de 17 camas operativas.
        $level1 = $this->getJson("/api/v1/reportes/ocupacion?desde={$today}&hasta={$today}", $headers)->assertOk()
            ->assertJsonPath('estado', 'exito')->assertJsonPath('datos.indicador.unidad', '%')->assertJsonPath('datos.indicador.numerador', 3)
            ->assertJsonPath('datos.indicador.denominador', 17)->assertHeader('X-Correlation-Id');
        $this->assertEqualsWithDelta(3 / 17 * 100, $level1->json('datos.indicador.valor'), 0.0001, 'Se calcula con el valor completo; redondea la pantalla');
        foreach (['formula', 'periodo', 'filtros', 'fuente', 'calculado_en'] as $field) {
            $this->assertNotEmpty($level1->json("datos.trazabilidad.{$field}"), "RN-05: falta {$field}");
        }

        // RN-07: el desglose suma el numerador. Por tipo de cama no se agrupa (dimensión administrativa).
        $level2 = $this->getJson("/api/v1/reportes/ocupacion/desglose?desde={$today}&hasta={$today}&dimension=tipo_cama", $headers)->assertOk();
        $this->assertSame(3, $level2->json('datos.desglose.total'));
        $this->assertTrue($level2->json('datos.desglose.verificado'));
        $this->assertSame(['General' => 2, 'Pediátrica' => 1], collect($level2->json('datos.desglose.partes'))->pluck('valor', 'parte')->all());

        // RN-04: por sala cada una tiene 1 caso, así que se agrupan sin cambiar el total.
        $bySala = $this->getJson("/api/v1/reportes/ocupacion/desglose?desde={$today}&hasta={$today}", $headers)->assertOk();
        $this->assertTrue($bySala->json('datos.desglose.anonimizado'));
        $this->assertSame([AnalyticsService::OTHERS => 3], collect($bySala->json('datos.desglose.partes'))->pluck('valor', 'parte')->all());

        // Nivel 3: la suma de los registros es la parte, y el paciente va solo con su código (RN-09).
        $level3 = $this->getJson("/api/v1/reportes/ocupacion/registros?desde={$today}&hasta={$today}&dimension=tipo_cama&parte=General", $headers)->assertOk();
        $this->assertSame(2, $level3->json('datos.registros.total'));
        $this->assertSame(2, $level3->json('datos.registros.suma'));
        $codes = LocalPatient::query()->pluck('code')->all();
        foreach ($level3->json('datos.registros.filas') as $row) {
            $this->assertContains($row['paciente'], $codes);
            $this->assertSame(['cama', 'sala', 'paciente', 'ingreso', 'egreso', 'peso'], array_keys($row), 'Solo datos mínimos, sin nombre ni motivo clínico');
        }
        $this->getJson("/api/v1/reportes/ocupacion/registros?desde={$today}&hasta={$today}&dimension=sala&parte=".urlencode(AnalyticsService::OTHERS), $headers)
            ->assertOk()->assertJsonPath('datos.registros.total', 3);
    }

    public function test_filters_and_requests_are_validated_with_their_codes(): void
    {
        $headers = $this->as('direccion@nido.test');
        $today = $this->today();

        $this->getJson("/api/v1/reportes/citas?desde={$today}&hasta=2020-01-01", $headers)->assertStatus(422)->assertJsonPath('code', 'PERIODO_INVALIDO');
        $this->getJson('/api/v1/reportes/citas?desde=2024-01-01&hasta=2026-10-31', $headers)->assertStatus(422)->assertJsonPath('code', 'PERIODO_MUY_LARGO');
        $this->getJson('/api/v1/reportes/citas?desde=1/10/2026&hasta=2026-10-31', $headers)->assertStatus(422)->assertJsonValidationErrors('desde');
        $this->getJson('/api/v1/reportes/citas?desde=2026-02-30&hasta=2026-03-01', $headers)->assertStatus(422)->assertJsonValidationErrors('desde');
        $this->getJson('/api/v1/reportes/citas', $headers)->assertStatus(422)->assertJsonValidationErrors(['desde', 'hasta']);
        $this->getJson("/api/v1/reportes/inventado?desde={$today}&hasta={$today}", $headers)->assertNotFound()->assertJsonPath('code', 'REPORTE_NO_EXISTE');
        $this->getJson("/api/v1/reportes/ocupacion/desglose?desde={$today}&hasta={$today}&dimension=medico", $headers)
            ->assertStatus(422)->assertJsonPath('code', 'DIMENSION_NO_DECLARADA');
        $this->getJson("/api/v1/reportes/ocupacion/registros?desde={$today}&hasta={$today}", $headers)->assertStatus(422)->assertJsonValidationErrors('parte');
        // Defecto D-01 del prototipo, corregido: una parte que no existe es un error, no una lista vacía.
        $this->getJson("/api/v1/reportes/ocupacion/registros?desde={$today}&hasta={$today}&dimension=tipo_cama&parte=Urgencias", $headers)
            ->assertStatus(422)->assertJsonPath('code', 'PARTE_NO_EXISTE');
        $this->getJson('/api/v1/reportes/ocupacion?desde=2026-01-01&hasta=2026-06-30&granularidad=dia', $headers)
            ->assertStatus(422)->assertJsonPath('code', 'GRANULARIDAD_EXCESIVA');
    }

    public function test_a_period_without_data_says_so_instead_of_showing_zero(): void
    {
        $this->getJson('/api/v1/reportes/pacientes?desde=2020-01-01&hasta=2020-01-31', $this->as('direccion@nido.test'))->assertOk()
            ->assertJsonPath('estado', 'sin_datos')->assertJsonPath('datos', null)->assertJsonPath('meta.clave', 'pacientes');
    }

    public function test_trend_and_comparison_use_the_same_formula_per_period(): void
    {
        $from = CarbonImmutable::parse($this->today())->subDays(6)->toDateString();
        $response = $this->getJson("/api/v1/reportes/ocupacion?desde={$from}&hasta={$this->today()}&granularidad=dia", $this->as('direccion@nido.test'))->assertOk();

        $points = $response->json('datos.tendencia.puntos');
        $this->assertCount(7, $points);
        $this->assertSame(array_sum(array_column($points, 'numerador')), $response->json('datos.indicador.numerador'), 'Los días-cama de cada día suman los del período');
        $this->assertNotNull($response->json('datos.comparacion.periodo'));
    }

    public function test_length_of_stay_and_mortality_split_numerator_and_denominator(): void
    {
        $patients = LocalPatient::query()->orderBy('code')->get();
        $now = CarbonImmutable::now();
        // Cuatro egresos de ayer: 2, 4, 6 y 3 días de estancia; uno por defunción.
        foreach ([[0, 2, 'medica'], [1, 4, 'medica'], [4, 6, 'referencia'], [5, 3, 'defuncion']] as [$n, $days, $type]) {
            CareEpisode::query()->create(['local_patient_id' => $patients[$n]->local_patient_id, 'type' => 'inpatient', 'status' => 'closed',
                'started_at' => $now->subDays($days + 1), 'ended_at' => $now->subDay(), 'reason' => 'Internamiento de prueba', 'discharge_type' => $type]);
        }
        $headers = $this->as('direccion@nido.test');
        $from = CarbonImmutable::parse($this->today())->subDays(3)->toDateString();
        $query = "desde={$from}&hasta={$this->today()}";

        $alos = $this->getJson("/api/v1/reportes/estancia_promedio?{$query}", $headers)->assertOk()
            ->assertJsonPath('datos.indicador.numerador', 15)->assertJsonPath('datos.indicador.denominador', 4);
        $this->assertEqualsWithDelta(3.75, $alos->json('datos.indicador.valor'), 0.0001);

        $this->getJson("/api/v1/reportes/egresos/desglose?{$query}&dimension=tipo_alta", $headers)->assertOk()->assertJsonPath('datos.desglose.total', 4);

        $mortality = $this->getJson("/api/v1/reportes/mortalidad/desglose?{$query}", $headers)->assertOk()
            ->assertJsonPath('datos.indicador.numerador', 1)->assertJsonPath('datos.indicador.denominador', 4);
        $this->assertEqualsWithDelta(25.0, $mortality->json('datos.indicador.valor'), 0.0001);
        // CA-06: la sala con una defunción queda en «Otros» y el total no cambia.
        $this->assertSame([AnalyticsService::OTHERS => 1], collect($mortality->json('datos.desglose.partes'))->pluck('valor', 'parte')->all());
    }

    public function test_doctor_sees_only_the_appointments_indicator_and_only_their_own(): void
    {
        $own = $this->doctor('medico@nido.test');
        $other = $this->doctor('pediatria@nido.test');
        $patients = LocalPatient::query()->orderBy('code')->get();
        foreach ([[$own, 'MEDICINA_GENERAL', 8, 0], [$own, 'MEDICINA_GENERAL', 9, 1], [$other, 'PEDIATRIA', 8, 2]] as [$doctor, $specialty, $hour, $n]) {
            $start = $this->nextMonday($hour)->utc();
            Appointment::query()->create(['local_patient_id' => $patients[$n]->local_patient_id, 'doctor_profile_id' => $doctor->doctor_profile_id,
                'specialty_id' => $this->specialty($specialty), 'starts_at' => $start, 'ends_at' => $start->addMinutes(30), 'status' => 'scheduled',
                'reason' => 'Control', 'booked_by_user_ref_id' => $doctor->local_user_ref_id]);
        }
        $query = 'desde='.$this->today().'&hasta='.$this->nextMonday(8)->addDay()->toDateString();

        $this->getJson("/api/v1/reportes/citas?{$query}", $this->as('direccion@nido.test'))->assertOk()->assertJsonPath('datos.indicador.numerador', 3);

        $doctor = $this->as('medico@nido.test');
        $this->getJson('/api/v1/reportes/indicadores', $doctor)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.clave', 'citas')->assertJsonPath('pendientes', []);
        $mine = $this->getJson("/api/v1/reportes/citas/registros?{$query}&dimension=estado&parte=Programada", $doctor)->assertOk()
            ->assertJsonPath('datos.indicador.numerador', 2)->assertJsonPath('datos.registros.total', 2);
        $this->assertStringContainsString('Solo las citas de', $mine->json('datos.trazabilidad.filtros'));
        $this->getJson("/api/v1/reportes/ocupacion?{$query}", $doctor)->assertForbidden()->assertJsonPath('code', 'SIN_PERMISO');
        // CA-07: pedir las citas de otro médico es 403, también en el desglose.
        $this->getJson("/api/v1/reportes/citas/desglose?{$query}&medico={$other->doctor_profile_id}", $doctor)->assertForbidden()->assertJsonPath('code', 'SIN_PERMISO');

        $this->getJson("/api/v1/reportes/citas?{$query}", $this->as('enfermeria@nido.test'))->assertForbidden()->assertJsonPath('code', 'PERMISO_NO_ASIGNADO');
    }

    public function test_every_indicator_meets_the_same_contract(): void
    {
        $headers = $this->as('direccion@nido.test');
        $from = CarbonImmutable::parse($this->today())->subDays(30)->toDateString();
        $query = "desde={$from}&hasta={$this->today()}";

        $catalog = $this->getJson('/api/v1/reportes/indicadores', $headers)->assertOk();
        $this->assertCount(count(IndicatorRegistry::PENDING), $catalog->json('pendientes'), 'Los indicadores que esperan a los módulos por integrar');
        $summary = collect($this->getJson("/api/v1/reportes/indicadores/resumen?{$query}", $headers)->assertOk()->json('data'))->keyBy('clave');

        foreach ($catalog->json('data') as $meta) {
            $this->assertContains($meta['unidad'], ['%', 'cantidad', 'dias']);
            $this->assertNotEmpty($meta['dimensiones'], "{$meta['clave']} declara sus dimensiones");
            $this->assertArrayHasKey($meta['clave'], $summary->all());
            foreach ($meta['dimensiones'] as $dimension) {
                $body = $this->getJson("/api/v1/reportes/{$meta['clave']}/desglose?{$query}&dimension={$dimension['clave']}", $headers)->assertOk()->json();
                if ($body['estado'] === 'sin_datos') {
                    $this->assertNull($body['datos']);

                    continue;
                }
                $this->assertSame($body['datos']['indicador']['numerador'], $body['datos']['desglose']['total'], "RN-07 en {$meta['clave']} por {$dimension['clave']}");
                if ($meta['unidad'] === '%') {
                    $this->assertGreaterThanOrEqual(0, $body['datos']['indicador']['valor']);
                    $this->assertLessThanOrEqual(100, $body['datos']['indicador']['valor']);
                }
            }
        }
    }
}

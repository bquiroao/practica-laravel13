<?php

namespace App\Services\Analytics;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\DoctorProfile;
use App\Services\Access\PermissionResolver;
use Carbon\CarbonImmutable;

/**
 * Módulo reportes y analytics básicos: cálculo y visualización trazable de un indicador, en tres niveles
 * (indicador, desglose y registros de origen), siempre con los mismos filtros y permisos (RN-08).
 * Es de solo lectura: no escribe en las tablas de otros módulos (RNF-04).
 */
class AnalyticsService
{
    /** Partes con menos casos que esto se agrupan (RN-04). */
    public const MIN_GROUP = 3;

    public const OTHERS = 'Otros (menos de 3 casos cada uno)';

    public function __construct(private readonly IndicatorRegistry $registry, private readonly PermissionResolver $permissions) {}

    /** Quien tiene reportes.ver consulta todo; quien solo tiene reportes.citas_propias es un médico con sus citas. */
    public function seesAll(CentralUser $user, Hospital $hospital): bool
    {
        return $this->permissions->allows($user->user_id, $hospital->hospital_id, 'reportes.ver');
    }

    /** @return list<Indicator> */
    public function allowed(CentralUser $user, Hospital $hospital): array
    {
        $all = $this->seesAll($user, $hospital);

        return array_values(array_filter($this->registry->all(), fn (Indicator $i) => $all || $i->doctorScoped()));
    }

    /** RN-01: el rol solo consulta los indicadores que tiene permitidos. */
    public function indicator(CentralUser $user, Hospital $hospital, string $key): Indicator
    {
        $indicator = $this->registry->get($key);
        if (! $this->seesAll($user, $hospital) && ! $indicator->doctorScoped()) {
            throw new DomainRuleException('SIN_PERMISO', 'Su rol solo puede consultar el indicador de citas, con sus propias citas.', 403);
        }

        return $indicator;
    }

    /**
     * Filtros de la consulta. Al Médico se le fija su propio perfil; si pide el de otro médico se rechaza (RN-08, CA-07).
     * Quien ve todo puede filtrar las citas por un médico.
     */
    public function filters(CentralUser $user, Hospital $hospital, Indicator $indicator, string $from, string $to, ?string $doctorId): Filters
    {
        $filters = Filters::make($from, $to, $hospital->timezone ?: config('app.timezone'));
        if ($this->seesAll($user, $hospital)) {
            if ($doctorId === null || ! $indicator->doctorScoped()) {
                return $filters;
            }
            $doctor = DoctorProfile::query()->with('userRef')->find($doctorId)
                ?? throw new DomainRuleException('MEDICO_NO_EXISTE', 'Ese médico no existe en este hospital.', 422);

            return $filters->forDoctor($doctor->doctor_profile_id, $doctor->name());
        }

        $own = DoctorProfile::query()->with('userRef')->whereHas('userRef', fn ($q) => $q->where('central_user_id', $user->user_id))->first()
            ?? throw new DomainRuleException('SIN_PERFIL_MEDICO', 'Su usuario no tiene perfil de médico en este hospital, así que no tiene citas propias.', 403);
        if ($doctorId !== null && $doctorId !== $own->doctor_profile_id) {
            throw new DomainRuleException('SIN_PERMISO', 'Solo puede consultar sus propias citas.', 403);
        }

        return $filters->forDoctor($own->doctor_profile_id, $own->name());
    }

    /** Resumen para las tarjetas: valor y comparación de cada indicador permitido, en una sola llamada. */
    public function summary(CentralUser $user, Hospital $hospital, string $from, string $to): array
    {
        return array_map(function (Indicator $indicator) use ($user, $hospital, $from, $to) {
            $filters = $this->filters($user, $hospital, $indicator, $from, $to, null);
            $current = $this->evaluate($indicator, $filters);

            return $this->meta($indicator) + [
                'estado' => $current === null ? 'sin_datos' : 'exito',
                'valor' => $current['valor'] ?? null,
                'numerador' => $current['numerador'] ?? null,
                'denominador' => $current['denominador'] ?? null,
                'comparacion' => $current === null ? null : $this->comparison($indicator, $filters, $current['valor']),
                'solo_medico' => $filters->doctorName,
            ];
        }, $this->allowed($user, $hospital));
    }

    /** Nivel 1: valor, comparación con el período anterior, tendencia y ficha de trazabilidad (RN-05). */
    public function level1(Indicator $indicator, Filters $filters, ?string $granularity): array
    {
        // Los filtros se validan antes de calcular: un pedido inválido es 422 haya o no datos (RN-02, RN-03).
        $granularity ??= $filters->defaultGranularity();
        $buckets = $filters->buckets($granularity);
        $current = $this->evaluate($indicator, $filters);
        if ($current === null) {
            return $this->noData($indicator, $filters);
        }

        return $this->success($indicator, $filters, $current, [
            'comparacion' => $this->comparison($indicator, $filters, $current['valor']),
            'tendencia' => ['granularidad' => $granularity, 'puntos' => array_map(function (array $b) use ($indicator) {
                $point = $this->evaluate($indicator, $b['filters']);

                return ['desde' => $b['desde'], 'hasta' => $b['hasta'], 'parcial' => $b['parcial'], 'valor' => $point['valor'] ?? null, 'numerador' => $point['numerador'] ?? null];
            }, $buckets)],
        ], ['Tendencia '.mb_strtolower(Filters::GRANULARITIES[$granularity])]);
    }

    /** Nivel 2: el numerador repartido por una dimensión; la suma de las partes es el numerador (RN-07). */
    public function level2(Indicator $indicator, Filters $filters, ?string $dimension): array
    {
        $dimension = $this->dimension($indicator, $dimension);
        $current = $this->evaluate($indicator, $filters);
        if ($current === null) {
            return $this->noData($indicator, $filters);
        }

        return $this->success($indicator, $filters, $current, ['desglose' => $this->breakdown($indicator, $current, $dimension)],
            ['Desglose por '.mb_strtolower($indicator->dimensions()[$dimension])]);
    }

    /** Nivel 3: los registros que forman una parte del desglose, con datos mínimos (RN-09). */
    public function level3(Indicator $indicator, Filters $filters, ?string $dimension, string $part, int $page, int $limit): array
    {
        $dimension = $this->dimension($indicator, $dimension);
        $current = $this->evaluate($indicator, $filters);
        if ($current === null) {
            return $this->noData($indicator, $filters);
        }
        $breakdown = $this->breakdown($indicator, $current, $dimension);
        $match = collect($breakdown['partes'])->firstWhere('parte', $part)
            // Defecto D-01 del prototipo: una parte que no existe no es «lista vacía», es un error del pedido.
            ?? throw new DomainRuleException('PARTE_NO_EXISTE', "«{$part}» no aparece en el desglose por ".mb_strtolower($indicator->dimensions()[$dimension]).'. Elija una parte de la tabla.');

        $small = $breakdown['agrupadas'];
        $rows = array_values(array_filter($current['items'], function (array $item) use ($dimension, $part, $small) {
            $label = $item['dims'][$dimension] ?? 'Sin dato';

            return $part === self::OTHERS ? in_array($label, $small, true) : $label === $part;
        }));
        $pages = max(1, (int) ceil(count($rows) / $limit));
        $page = min($page, $pages);
        $columns = $indicator->columns();

        return $this->success($indicator, $filters, $current, [
            'desglose' => $breakdown,
            'registros' => [
                'parte' => $part,
                'columnas' => array_map(fn ($k, $v) => ['clave' => $k, 'etiqueta' => $v], array_keys($columns), $columns),
                'filas' => array_map(fn (array $r) => array_intersect_key($r['fila'], $columns), array_slice($rows, ($page - 1) * $limit, $limit)),
                'total' => count($rows),
                'suma' => array_sum(array_column($rows, 'peso')),
                'valor_parte' => $match['valor'],
                'pagina' => $page,
                'paginas' => $pages,
                'por_pagina' => $limit,
            ],
        ], ['Desglose por '.mb_strtolower($indicator->dimensions()[$dimension]), 'Parte: '.$part]);
    }

    public function meta(Indicator $indicator): array
    {
        return [
            'clave' => $indicator->key(), 'nombre' => $indicator->name(), 'area' => $indicator->area(), 'unidad' => $indicator->unit(),
            'descripcion' => $indicator->description(), 'formula' => $indicator->formula(),
            'dimensiones' => array_map(fn ($k, $v) => ['clave' => $k, 'etiqueta' => $v], array_keys($indicator->dimensions()), $indicator->dimensions()),
            'solo_citas_propias' => $indicator->doctorScoped(), 'modulos' => $indicator->feeds(),
        ];
    }

    /**
     * El cálculo, con valores completos: se redondea solo al mostrar, en un único lugar (C-10).
     *
     * @return array{items: list<array>, numerador: int, denominador: int|null, valor: float}|null null = sin datos (RN-06)
     */
    private function evaluate(Indicator $indicator, Filters $filters): ?array
    {
        $items = array_values(array_filter($indicator->items($filters), fn (array $i) => $i['peso'] > 0));
        $denominator = $indicator->denominator($filters, $items);
        if ($indicator->population($items, $denominator) === 0 || $denominator === 0) {
            return null;
        }
        $numerator = array_sum(array_column($items, 'peso'));
        $value = $denominator === null ? (float) $numerator : $numerator / $denominator * $indicator->factor();
        if ($indicator->unit() === '%') {
            $value = min(100.0, max(0.0, $value));
        }

        return ['items' => $items, 'numerador' => $numerator, 'denominador' => $denominator, 'valor' => $value];
    }

    private function comparison(Indicator $indicator, Filters $filters, float $value): array
    {
        $previous = $filters->previous();
        $before = $this->evaluate($indicator, $previous);

        return ['periodo' => $previous->label(), 'valor' => $before['valor'] ?? null, 'diferencia' => $before === null ? null : $value - $before['valor']];
    }

    private function breakdown(Indicator $indicator, array $current, string $dimension): array
    {
        $groups = [];
        foreach ($current['items'] as $item) {
            $label = $item['dims'][$dimension] ?? 'Sin dato';
            $groups[$label] ??= ['valor' => 0, 'casos' => 0];
            $groups[$label]['valor'] += $item['peso'];
            $groups[$label]['casos']++;
        }
        $small = $indicator->groupsSmallCases($dimension) ? array_keys(array_filter($groups, fn ($g) => $g['casos'] < self::MIN_GROUP)) : [];
        $parts = [];
        foreach ($groups as $label => $g) {
            if (! in_array($label, $small, true)) {
                $parts[] = ['parte' => (string) $label, 'valor' => $g['valor'], 'casos' => $g['casos']];
            }
        }
        usort($parts, fn ($a, $b) => [$b['valor'], $a['parte']] <=> [$a['valor'], $b['parte']]);
        if ($small !== []) {
            $parts[] = ['parte' => self::OTHERS, 'valor' => array_sum(array_map(fn ($l) => $groups[$l]['valor'], $small)),
                'casos' => array_sum(array_map(fn ($l) => $groups[$l]['casos'], $small)), 'grupos' => count($small)];
        }
        $total = array_sum(array_column($parts, 'valor'));

        return [
            'dimension' => $dimension, 'etiqueta' => $indicator->dimensions()[$dimension], 'unidad_partes' => $indicator->numeratorLabel(),
            'partes' => $parts, 'total' => $total, 'verificado' => $total === $current['numerador'],
            'anonimizado' => $small !== [], 'agrupadas' => array_map('strval', $small),
        ];
    }

    private function dimension(Indicator $indicator, ?string $dimension): string
    {
        $dimensions = $indicator->dimensions();
        $dimension ??= array_key_first($dimensions);
        if (! array_key_exists($dimension, $dimensions)) {
            throw new DomainRuleException('DIMENSION_NO_DECLARADA', $indicator->name().' se puede desglosar por: '.implode(', ', array_map('mb_strtolower', $dimensions)).'.');
        }

        return $dimension;
    }

    /** @param list<string> $extra filtros de este nivel, para la ficha */
    private function success(Indicator $indicator, Filters $filters, array $current, array $data, array $extra = []): array
    {
        $detail = $current['numerador'].' '.$indicator->numeratorLabel()
            .($current['denominador'] === null ? '' : ' de '.$current['denominador'].' '.$indicator->denominatorLabel());

        return ['estado' => 'exito', 'meta' => $this->meta($indicator), 'datos' => [
            'indicador' => ['nombre' => $indicator->name(), 'valor' => $current['valor'], 'unidad' => $indicator->unit(), 'numerador' => $current['numerador'],
                'denominador' => $current['denominador'], 'etiqueta_numerador' => $indicator->numeratorLabel(), 'etiqueta_denominador' => $indicator->denominatorLabel()],
            'trazabilidad' => array_filter([
                'formula' => $indicator->formula(),
                'periodo' => $filters->label().($filters->to > $filters->now ? ' (datos hasta el momento de la consulta)' : ''),
                'filtros' => implode(' · ', array_filter([$filters->doctorName ? 'Solo las citas de '.$filters->doctorName : null, ...$extra])) ?: 'ninguno adicional',
                'fuente' => $indicator->source(),
                'calculado_en' => CarbonImmutable::now()->toIso8601String(),
                'detalle' => $detail,
                'nota' => $indicator->note(),
            ], fn ($v) => $v !== null),
        ] + $data];
    }

    private function noData(Indicator $indicator, Filters $filters): array
    {
        return ['estado' => 'sin_datos', 'meta' => $this->meta($indicator),
            'mensaje' => 'Sin datos de «'.mb_strtolower($indicator->name()).'» del '.$filters->label().($filters->doctorName ? ' para '.$filters->doctorName : '').'. Pruebe con otro período.',
            'datos' => null];
    }
}

<?php

namespace App\Services\Analytics;

use App\Exceptions\DomainRuleException;
use Carbon\CarbonImmutable;

/**
 * Módulo reportes y analytics básicos: única precondición común de todos los indicadores (RN-02).
 * Las fechas se leen en la zona del hospital; los datos llegan hasta el momento de la consulta.
 */
final class Filters
{
    /** Contrato API v1: período de hasta 366 días (corrige el defecto D-02 del prototipo). */
    public const MAX_DAYS = 366;

    /** Puntos de la tendencia: más de esto no se lee en un gráfico ni responde en menos de 2 s (RNF-01). */
    public const MAX_BUCKETS = 62;

    public const GRANULARITIES = ['dia' => 'Por día', 'semana' => 'Por semana', 'mes' => 'Por mes'];

    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $tz,
        public readonly CarbonImmutable $now,
        public readonly ?string $doctorProfileId = null,
        public readonly ?string $doctorName = null,
    ) {}

    public static function make(string $desde, string $hasta, string $tz, ?CarbonImmutable $now = null): self
    {
        $from = CarbonImmutable::parse($desde, $tz)->startOfDay();
        $to = CarbonImmutable::parse($hasta, $tz)->endOfDay();
        if ($to < $from) {
            throw new DomainRuleException('PERIODO_INVALIDO', 'La fecha inicial no puede ser mayor que la final. Cambie «Desde» o «Hasta».');
        }
        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            throw new DomainRuleException('PERIODO_MUY_LARGO', 'El período puede ser de hasta '.self::MAX_DAYS.' días. Elija un rango más corto.');
        }

        return new self($from, $to, $tz, $now ?? CarbonImmutable::now($tz));
    }

    /** El Médico solo rastrea sus propias citas (RN-08): el filtro lo fija el servidor, no el cliente. */
    public function forDoctor(string $doctorProfileId, string $name): self
    {
        return new self($this->from, $this->to, $this->tz, $this->now, $doctorProfileId, $name);
    }

    private function between(CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self($from, $to, $this->tz, $this->now, $this->doctorProfileId, $this->doctorName);
    }

    /** Fin efectivo del período: no se cuentan datos del futuro. */
    public function end(): CarbonImmutable
    {
        return $this->to->min($this->now);
    }

    public function started(): bool
    {
        return $this->from <= $this->now;
    }

    /**
     * Instantes para consultar la base, con microsegundos y zona: si se pasaran como Carbon, Laravel los recorta al
     * segundo y quedaría fuera lo registrado en el mismo segundo de la consulta.
     *
     * @return array{0: string, 1: string}
     */
    public function range(): array
    {
        return [$this->from->utc()->format('Y-m-d H:i:s.uP'), $this->end()->utc()->format('Y-m-d H:i:s.uP')];
    }

    /** Días de calendario transcurridos del período (hasta hoy). */
    public function elapsedDays(): int
    {
        return $this->started() ? (int) $this->from->startOfDay()->diffInDays($this->end()->startOfDay()) + 1 : 0;
    }

    public function localDate(mixed $instant): string
    {
        return CarbonImmutable::parse($instant)->setTimezone($this->tz)->toDateString();
    }

    public function localDateTime(mixed $instant): string
    {
        return CarbonImmutable::parse($instant)->setTimezone($this->tz)->format('Y-m-d H:i');
    }

    public function label(): string
    {
        return $this->from->toDateString().' a '.$this->to->toDateString();
    }

    /** Mismo largo, inmediatamente antes: para la comparación del indicador. */
    public function previous(): self
    {
        $days = (int) $this->from->diffInDays($this->to->startOfDay()) + 1;

        return $this->between($this->from->subDays($days), $this->from->subDay()->endOfDay());
    }

    /** Granularidad automática: día hasta un mes, semana hasta medio año y mes en adelante. */
    public function defaultGranularity(): string
    {
        $days = (int) $this->from->diffInDays($this->to->startOfDay()) + 1;

        return $days <= 31 ? 'dia' : ($days <= 186 ? 'semana' : 'mes');
    }

    /**
     * Cortes de la tendencia (RN-03). Las semanas van de lunes a domingo; la primera y la última pueden quedar
     * parciales y se marcan así, porque no se comparan igual que una completa.
     *
     * @return list<array{filters: self, desde: string, hasta: string, parcial: bool}>
     */
    public function buckets(string $granularity): array
    {
        $out = [];
        $cursor = $this->from;
        while ($cursor <= $this->to) {
            [$start, $end] = match ($granularity) {
                'dia' => [$cursor->startOfDay(), $cursor->endOfDay()],
                'semana' => [$cursor->startOfWeek(CarbonImmutable::MONDAY), $cursor->endOfWeek(CarbonImmutable::SUNDAY)],
                'mes' => [$cursor->startOfMonth(), $cursor->endOfMonth()],
            };
            $clippedStart = $start->max($this->from);
            $clippedEnd = $end->min($this->to);
            $out[] = ['filters' => $this->between($clippedStart, $clippedEnd), 'desde' => $clippedStart->toDateString(), 'hasta' => $clippedEnd->toDateString(),
                'parcial' => $clippedStart->ne($start) || $clippedEnd->ne($end)];
            if (count($out) > self::MAX_BUCKETS) {
                throw new DomainRuleException('GRANULARIDAD_EXCESIVA', 'Hay demasiados puntos para la tendencia. Elija «Por semana» o «Por mes».');
            }
            $cursor = $end->addSecond();
        }

        return $out;
    }
}

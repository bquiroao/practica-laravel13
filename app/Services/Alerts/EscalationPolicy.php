<?php

namespace App\Services\Alerts;

use App\Models\Hospital\CriticalAlertPolicy;
use Carbon\CarbonImmutable;

/**
 * Plazos y niveles de escalamiento (RN-04, RN-05, RN-11). Nivel 0: enfermería y médico tratante; nivel 1: médicos
 * del hospital (hacen de jefe de servicio); nivel 2: dirección médica, el último.
 */
class EscalationPolicy
{
    public const MAX_LEVEL = 2;

    public const MIN_MINUTES = 5;

    public const MAX_MINUTES = 60;

    public const DEFAULT_MINUTES = ['CRITICA' => 15, 'ALTA' => 30];

    public const MAX_ATTEMPTS = 3;

    public const LEVELS = [0 => 'Enfermería y médico tratante', 1 => 'Médicos del hospital', 2 => 'Dirección médica'];

    public function minutes(string $priority): int
    {
        return CriticalAlertPolicy::query()->whereKey($priority)->value('ack_minutes') ?? self::DEFAULT_MINUTES[$priority];
    }

    /** Hasta cuándo se espera el acuse en el nivel actual: vencido en ese mismo minuto (CA-10: a las 10:15 ya escala). */
    public function deadline(string $priority, ?CarbonImmutable $from = null): CarbonImmutable
    {
        return ($from ?? CarbonImmutable::now())->addMinutes($this->minutes($priority));
    }
}

<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Se dispara al validar un resultado de laboratorio crítico. Lo escucha el módulo de alertas críticas. */
class LabResultCritical
{
    use Dispatchable;

    public function __construct(
        public readonly string $hospitalId,
        public readonly string $patientId,
        public readonly string $patientCode,
        public readonly string $testCode,
        public readonly string $testName,
        public readonly float $value,
        public readonly string $unit,
        public readonly string $flag,
    ) {}
}

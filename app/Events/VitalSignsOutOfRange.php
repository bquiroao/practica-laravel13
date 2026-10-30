<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara al registrar signos vitales con algún valor anormal o crítico. Lo escucha el módulo de alertas
 * críticas (alertas-criticas-notificaciones-internas) para crear y escalar la alerta, sin acoplar los módulos.
 *
 * @param  list<array{signo: string, etiqueta: string, valor: float, nivel: 'anormal'|'critica'}>  $findings
 */
class VitalSignsOutOfRange
{
    use Dispatchable;

    public function __construct(
        public readonly string $hospitalId,
        public readonly string $patientId,
        public readonly string $patientCode,
        public readonly string $careEpisodeId,
        public readonly string $vitalSignId,
        public readonly array $findings,
    ) {}

    public function isCritical(): bool
    {
        return collect($this->findings)->contains('nivel', 'critica');
    }
}

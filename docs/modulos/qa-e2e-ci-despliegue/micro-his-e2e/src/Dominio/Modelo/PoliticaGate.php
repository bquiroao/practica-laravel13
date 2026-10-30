<?php

declare(strict_types=1);

namespace E2e\Dominio\Modelo;

/** R-09: los umbrales son datos versionados de la política, no constantes en el código. */
final readonly class PoliticaGate
{
    public function __construct(
        public string $version,
        public float $umbralNoCriticos,
        public int $maxInestables,
        public int $antiguedadMaximaHoras,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace E2e\Dominio\Modelo;

/** Resultado de un escenario en una ejecución. INESTABLE no lo reporta el pipeline: lo deduce el dominio (R-06). */
enum Resultado: string
{
    case PASA = 'PASA';
    case FALLA = 'FALLA';
    case INESTABLE = 'INESTABLE';
    case OMITIDO = 'OMITIDO';

    public function exigeEvidencia(): bool
    {
        return $this === self::FALLA || $this === self::INESTABLE;
    }
}

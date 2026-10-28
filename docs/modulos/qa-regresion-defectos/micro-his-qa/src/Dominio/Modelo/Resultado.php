<?php

declare(strict_types=1);

namespace Qa\Dominio\Modelo;

/** Resultado de un intento. FALLA y BLOQUEADO se tienen que poder revisar (R-05). */
enum Resultado: string
{
    case PASA = 'PASA';
    case FALLA = 'FALLA';
    case BLOQUEADO = 'BLOQUEADO';
    case NO_APLICA = 'NO_APLICA';

    public function exigeEvidencia(): bool
    {
        return $this === self::FALLA || $this === self::BLOQUEADO;
    }
}

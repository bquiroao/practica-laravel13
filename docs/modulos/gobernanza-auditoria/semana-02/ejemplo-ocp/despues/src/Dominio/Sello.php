<?php

declare(strict_types=1);

namespace Auditoria\Dominio;

/** R-03: cada sello depende del anterior del mismo hospital; alterar uno rompe todos los siguientes. */
final class Sello
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public static function calcular(string $selloAnterior, int $secuencia, Movimiento $movimiento): string
    {
        return hash('sha256', $selloAnterior . $secuencia . json_encode($movimiento->contenido()));
    }
}

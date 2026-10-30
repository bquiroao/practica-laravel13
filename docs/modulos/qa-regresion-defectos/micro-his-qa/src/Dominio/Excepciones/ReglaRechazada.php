<?php

declare(strict_types=1);

namespace Qa\Dominio\Excepciones;

/** Una regla del dominio rechazó la operación. $detalle dice qué falta (campos, casos críticos, casos pendientes). */
final class ReglaRechazada extends \DomainException
{
    /** @param list<string> $detalle */
    public function __construct(public readonly string $codigo, public readonly array $detalle = [])
    {
        parent::__construct($codigo . ($detalle === [] ? '' : ': ' . implode(', ', $detalle)));
    }
}

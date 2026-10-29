<?php

declare(strict_types=1);

namespace E2e\Dominio\Excepciones;

/** Una regla de negocio rechazó la operación; el código es estable y el detalle dice qué (campos, escenarios). */
final class ReglaRechazada extends \DomainException
{
    /** @param list<string> $detalle */
    public function __construct(public readonly string $codigo, public readonly array $detalle = [])
    {
        parent::__construct($codigo . ($detalle === [] ? '' : ': ' . implode(', ', $detalle)));
    }
}

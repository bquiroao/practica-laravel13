<?php
declare(strict_types=1);

namespace Admision\Domain\Modelo;

/**
 * Cama candidata tal como la informa el catálogo de salas y camas (módulo de Josué).
 * Admisión solo la lee; el catálogo es el dueño de estos datos.
 */
final class CamaDisponible
{
    public function __construct(
        public readonly string $id,
        public readonly TenantId $tenantId,
        public readonly string $codigo,
        public readonly string $servicio,
        public readonly TipoCama $tipo,
        public readonly bool $aislamiento,
        public readonly bool $operativa,
    ) {
    }
}

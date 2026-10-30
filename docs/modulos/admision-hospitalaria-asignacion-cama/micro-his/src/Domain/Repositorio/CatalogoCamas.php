<?php
declare(strict_types=1);

namespace Admision\Domain\Repositorio;

use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\TenantId;

/**
 * Puerto de SOLO LECTURA hacia el catálogo de salas y camas (módulo de Josué).
 * Admisión nunca escribe en los datos del catálogo. Contrato pendiente de confirmar con él.
 */
interface CatalogoCamas
{
    public function buscar(TenantId $tenantId, string $camaId): ?CamaDisponible;

    /** @return list<CamaDisponible> */
    public function listar(TenantId $tenantId): array;
}

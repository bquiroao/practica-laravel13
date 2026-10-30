<?php
declare(strict_types=1);

namespace Admision\Persistence\InMemory;

use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\TenantId;
use Admision\Domain\Modelo\TipoCama;
use Admision\Domain\Repositorio\CatalogoCamas;

/** Catálogo de camas en memoria, cargado con las filas de datos-ficticios.php. */
final class InMemoryCatalogoCamas implements CatalogoCamas
{
    /** @var list<CamaDisponible> */
    private array $camas;

    /** @param list<array{string,string,string,string,string,bool,bool}> $filas */
    public function __construct(array $filas)
    {
        $this->camas = array_map(
            fn (array $c) => new CamaDisponible($c[0], TenantId::desde($c[1]), $c[2], $c[3], TipoCama::from($c[4]), $c[5], $c[6]),
            $filas,
        );
    }

    public function buscar(TenantId $tenantId, string $camaId): ?CamaDisponible
    {
        foreach ($this->listar($tenantId) as $cama) {
            if ($cama->id === $camaId) {
                return $cama;
            }
        }
        return null;
    }

    public function listar(TenantId $tenantId): array
    {
        $camas = array_values(array_filter($this->camas, fn (CamaDisponible $c) => $c->tenantId->esIgualA($tenantId)));
        usort($camas, fn (CamaDisponible $a, CamaDisponible $b) => strcmp($a->codigo, $b->codigo));
        return $camas;
    }
}

<?php
declare(strict_types=1);

namespace Catalogo\Application;

use Catalogo\Domain\Modelo\CriterioBusqueda;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Domain\Puertos\Reloj;
use Catalogo\Domain\Repositorio\BusquedaCatalogo;

/** CU-02 Buscar medicamentos vigentes a una fecha (RF-04, RN-03). */
final class BuscarMedicamentosVigentes
{
    public function __construct(private readonly BusquedaCatalogo $busqueda, private readonly Reloj $reloj)
    {
    }

    /**
     * @param array<string, mixed> $filtros q, forma, fecha
     * @return list<Medicamento>
     */
    public function ejecutar(string $hospitalId, array $filtros): array
    {
        return $this->busqueda->buscarVigentes($hospitalId, CriterioBusqueda::desdeFiltros($filtros, $this->reloj->hoy()));
    }
}

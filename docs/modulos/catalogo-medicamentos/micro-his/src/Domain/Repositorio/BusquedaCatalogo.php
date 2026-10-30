<?php
declare(strict_types=1);

namespace Catalogo\Domain\Repositorio;

use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\CriterioBusqueda;
use Catalogo\Domain\Modelo\Medicamento;

/** ISP: lectura de lo vigente a una fecha (RN-03). */
interface BusquedaCatalogo
{
    /** @return list<Medicamento> ordenados por nombre y código, como máximo $criterio->limite */
    public function buscarVigentes(string $hospitalId, CriterioBusqueda $criterio): array;

    public function vigentePorCodigo(string $hospitalId, CodigoMedicamento $codigo, \DateTimeImmutable $fecha): ?Medicamento;
}

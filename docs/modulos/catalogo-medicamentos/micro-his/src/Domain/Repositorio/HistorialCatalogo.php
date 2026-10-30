<?php
declare(strict_types=1);

namespace Catalogo\Domain\Repositorio;

use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\Medicamento;

/** ISP: todas las versiones de un código. */
interface HistorialCatalogo
{
    /** @return list<Medicamento> de la versión 1 a la última; vacía si el código no existe */
    public function historial(string $hospitalId, CodigoMedicamento $codigo): array;
}

<?php
declare(strict_types=1);

namespace Catalogo\Domain\Repositorio;

use Catalogo\Domain\Modelo\ClaveEquivalencia;
use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\Vigencia;

/** ISP: detección de duplicados (RN-04). */
interface ControlEquivalencia
{
    /** Código de otra versión con la misma clave cuya vigencia se cruza con $vigencia, o null. */
    public function equivalenteVigente(string $hospitalId, ClaveEquivalencia $clave, Vigencia $vigencia, ?CodigoMedicamento $excluir = null): ?CodigoMedicamento;
}

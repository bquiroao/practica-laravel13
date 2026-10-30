<?php
declare(strict_types=1);

namespace Catalogo\Domain\Puertos;

use Catalogo\Domain\Modelo\CodigoMedicamento;

/**
 * Puerto hacia prescripciones-alergias (RN-02). Lo implementa un adaptador; el formato real de
 * la consulta está PENDIENTE de acordar con el dueño de ese módulo.
 */
interface ConsultaUsoPrescripciones
{
    public function fueUsado(string $hospitalId, CodigoMedicamento $codigo): bool;
}

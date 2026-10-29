<?php
declare(strict_types=1);

namespace Admision\Domain\Elegibilidad;

use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\Requerimiento;

final class CriterioCamaOperativa implements CriterioElegibilidadCama
{
    public function motivoDeRechazo(CamaDisponible $cama, Requerimiento $req): ?string
    {
        // RN-04: solo camas operativas según el catálogo.
        return $cama->operativa ? null : 'La cama no está operativa.';
    }
}

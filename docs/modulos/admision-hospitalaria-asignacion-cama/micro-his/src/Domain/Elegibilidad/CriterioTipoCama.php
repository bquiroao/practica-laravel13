<?php
declare(strict_types=1);

namespace Admision\Domain\Elegibilidad;

use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\Requerimiento;

final class CriterioTipoCama implements CriterioElegibilidadCama
{
    public function motivoDeRechazo(CamaDisponible $cama, Requerimiento $req): ?string
    {
        return $cama->tipo === $req->tipo ? null : 'El tipo de cama no coincide.';
    }
}

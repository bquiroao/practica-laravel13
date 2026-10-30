<?php
declare(strict_types=1);

namespace Admision\Domain\Elegibilidad;

use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\Requerimiento;

final class CriterioServicio implements CriterioElegibilidadCama
{
    public function motivoDeRechazo(CamaDisponible $cama, Requerimiento $req): ?string
    {
        return $cama->servicio === $req->servicio ? null : 'La cama es de otro servicio.';
    }
}

<?php
declare(strict_types=1);

namespace Admision\Domain\Elegibilidad;

use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\Requerimiento;

/** OCP (semana 2): cada criterio nuevo es una clase nueva; el evaluador no cambia. */
interface CriterioElegibilidadCama
{
    /** Devuelve null si la cama cumple, o el motivo de rechazo. */
    public function motivoDeRechazo(CamaDisponible $cama, Requerimiento $req): ?string;
}

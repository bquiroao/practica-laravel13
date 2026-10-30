<?php
declare(strict_types=1);

namespace Admision\Domain\Elegibilidad;

use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\Requerimiento;

final class CriterioAislamiento implements CriterioElegibilidadCama
{
    public function motivoDeRechazo(CamaDisponible $cama, Requerimiento $req): ?string
    {
        if ($cama->aislamiento === $req->requiereAislamiento) {
            return null;
        }
        return $req->requiereAislamiento
            ? 'El paciente requiere una cama de aislamiento.'
            : 'La cama de aislamiento se reserva para quien la requiere.';
    }
}

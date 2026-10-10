<?php

declare(strict_types=1);

namespace Rbac\Dominio\Reglas\Asignacion;

use Rbac\Dominio\Modelo\ErrorAsignacion;
use Rbac\Dominio\Modelo\SolicitudAsignacion;
use Rbac\Dominio\Reglas\ReglaAsignacion;

/** R-02: si la membresía tiene fecha de fin, debe ser posterior a la de inicio. */
final class ReglaVigenciaValida implements ReglaAsignacion
{
    public function evaluar(SolicitudAsignacion $solicitud): ?ErrorAsignacion
    {
        if ($solicitud->validoHasta !== null && $solicitud->validoHasta <= $solicitud->validoDesde) {
            return new ErrorAsignacion('VIGENCIA_INVALIDA', 422);
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Rbac\Reglas\Asignacion;

use Rbac\Contratos\ReglaAsignacion;
use Rbac\Dominio\ErrorAsignacion;
use Rbac\Dominio\SolicitudAsignacion;

/** D-03 / R-04: nadie puede asignarse roles a sí mismo. */
final class ReglaNoAutoasignacion implements ReglaAsignacion
{
    public function evaluar(SolicitudAsignacion $solicitud): ?ErrorAsignacion
    {
        return $solicitud->adminId === $solicitud->usuarioDestinoId
            ? new ErrorAsignacion('AUTOASIGNACION', 422)
            : null;
    }
}

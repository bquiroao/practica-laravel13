<?php

declare(strict_types=1);

namespace Rbac\Dominio\Reglas\Asignacion;

use Rbac\Dominio\Modelo\ErrorAsignacion;
use Rbac\Dominio\Modelo\SolicitudAsignacion;
use Rbac\Dominio\Reglas\ReglaAsignacion;

/** R-04: nadie puede asignarse roles a sí mismo. */
final class ReglaNoAutoasignacion implements ReglaAsignacion
{
    public function evaluar(SolicitudAsignacion $solicitud): ?ErrorAsignacion
    {
        return $solicitud->adminId === $solicitud->usuarioDestinoId
            ? new ErrorAsignacion('AUTOASIGNACION', 422)
            : null;
    }
}

<?php

declare(strict_types=1);

namespace Rbac\Dominio\Reglas\Revocacion;

use Rbac\Dominio\Modelo\ErrorRevocacion;
use Rbac\Dominio\Modelo\SolicitudRevocacion;
use Rbac\Dominio\Reglas\ReglaRevocacion;

/** Un administrador no puede revocarse a sí mismo: evita que el hospital quede sin administración. */
final class ReglaNoAutorrevocacion implements ReglaRevocacion
{
    public function evaluar(SolicitudRevocacion $solicitud): ?ErrorRevocacion
    {
        return $solicitud->adminId === $solicitud->usuarioDestinoId
            ? new ErrorRevocacion('AUTORREVOCACION', 422)
            : null;
    }
}

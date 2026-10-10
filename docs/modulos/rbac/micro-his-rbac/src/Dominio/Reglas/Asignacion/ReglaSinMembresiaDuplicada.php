<?php

declare(strict_types=1);

namespace Rbac\Dominio\Reglas\Asignacion;

use Rbac\Dominio\Modelo\ErrorAsignacion;
use Rbac\Dominio\Modelo\SolicitudAsignacion;
use Rbac\Dominio\Puertos\RepositorioMembresias;
use Rbac\Dominio\Reglas\ReglaAsignacion;

/** R-03: no puede haber dos membresías activas abiertas con el mismo usuario, hospital y rol. */
final class ReglaSinMembresiaDuplicada implements ReglaAsignacion
{
    public function __construct(private readonly RepositorioMembresias $membresias)
    {
    }

    public function evaluar(SolicitudAsignacion $solicitud): ?ErrorAsignacion
    {
        return $this->membresias->existeActiva($solicitud->usuarioDestinoId, $solicitud->hospitalId, $solicitud->rol)
            ? new ErrorAsignacion('ASIGNACION_DUPLICADA', 409)
            : null;
    }
}

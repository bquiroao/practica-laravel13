<?php

declare(strict_types=1);

namespace Rbac\Reglas\Asignacion;

use Rbac\Contratos\ReglaAsignacion;
use Rbac\Contratos\RepositorioMembresias;
use Rbac\Dominio\ErrorAsignacion;
use Rbac\Dominio\SolicitudAsignacion;

/** D-04 / R-03: no puede haber dos membresías activas con el mismo usuario, hospital y rol. */
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

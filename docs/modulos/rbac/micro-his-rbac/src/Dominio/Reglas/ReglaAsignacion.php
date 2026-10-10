<?php

declare(strict_types=1);

namespace Rbac\Dominio\Reglas;

use Rbac\Dominio\Modelo\ErrorAsignacion;
use Rbac\Dominio\Modelo\SolicitudAsignacion;

/** Punto de extensión (OCP) de las validaciones de asignación de rol. */
interface ReglaAsignacion
{
    public function evaluar(SolicitudAsignacion $solicitud): ?ErrorAsignacion;
}

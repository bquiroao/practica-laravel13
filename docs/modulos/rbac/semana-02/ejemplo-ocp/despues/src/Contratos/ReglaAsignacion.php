<?php

declare(strict_types=1);

namespace Rbac\Contratos;

use Rbac\Dominio\ErrorAsignacion;
use Rbac\Dominio\SolicitudAsignacion;

/** Punto de extensión de las validaciones de asignación de rol (OCP). */
interface ReglaAsignacion
{
    public function evaluar(SolicitudAsignacion $solicitud): ?ErrorAsignacion;
}

<?php

declare(strict_types=1);

namespace Rbac\Dominio\Reglas;

use Rbac\Dominio\Modelo\ErrorRevocacion;
use Rbac\Dominio\Modelo\SolicitudRevocacion;

/** Punto de extensión (OCP) de las validaciones de revocación de rol. */
interface ReglaRevocacion
{
    public function evaluar(SolicitudRevocacion $solicitud): ?ErrorRevocacion;
}

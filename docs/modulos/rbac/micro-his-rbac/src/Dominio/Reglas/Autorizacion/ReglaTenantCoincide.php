<?php

declare(strict_types=1);

namespace Rbac\Dominio\Reglas\Autorizacion;

use Rbac\Dominio\Modelo\ContextoAutorizacion;
use Rbac\Dominio\Reglas\ReglaAutorizacion;

/** R-01: el hospital del token debe ser el mismo de la cabecera X-Tenant-ID. */
final class ReglaTenantCoincide implements ReglaAutorizacion
{
    public function evaluar(ContextoAutorizacion $contexto): ?string
    {
        $solicitud = $contexto->solicitud;

        return $solicitud->tenantToken === $solicitud->tenantSolicitud ? null : 'TENANT_NO_COINCIDE';
    }
}

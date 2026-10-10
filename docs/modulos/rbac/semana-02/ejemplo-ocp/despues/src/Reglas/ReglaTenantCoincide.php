<?php

declare(strict_types=1);

namespace Rbac\Reglas;

use Rbac\Contratos\ReglaAutorizacion;
use Rbac\Dominio\ContextoAutorizacion;

/** D-05/R-01: el hospital del token debe ser el mismo de la cabecera X-Tenant-ID. */
final class ReglaTenantCoincide implements ReglaAutorizacion
{
    public function evaluar(ContextoAutorizacion $contexto): ?string
    {
        $solicitud = $contexto->solicitud;

        return $solicitud->tenantToken === $solicitud->tenantSolicitud ? null : 'TENANT_NO_COINCIDE';
    }
}

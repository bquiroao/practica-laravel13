<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Reglas;

use Auditoria\Dominio\Modelo\Rechazo;
use Auditoria\Dominio\Modelo\SolicitudRegistro;

/** R-05: un emisor solo escribe en el hospital de su token. Va primera: no da pistas de validación a otro tenant. */
final class ReglaTenantDelEmisor implements ReglaMovimiento
{
    public function evaluar(SolicitudRegistro $s): ?Rechazo
    {
        return ($s->datos['hospital'] ?? null) === $s->hospitalToken ? null : Rechazo::prohibido('TENANT_NO_COINCIDE');
    }
}

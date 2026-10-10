<?php

declare(strict_types=1);

namespace Rbac\Dominio\Reglas\Autorizacion;

use Rbac\Dominio\Modelo\ContextoAutorizacion;
use Rbac\Dominio\Reglas\ReglaAutorizacion;

/** R-02: debe existir al menos una membresía activa y dentro de su vigencia. */
final class ReglaMembresiaVigente implements ReglaAutorizacion
{
    public function evaluar(ContextoAutorizacion $contexto): ?string
    {
        return $contexto->membresiasVigentes() === [] ? 'MEMBRESIA_NO_VIGENTE' : null;
    }
}

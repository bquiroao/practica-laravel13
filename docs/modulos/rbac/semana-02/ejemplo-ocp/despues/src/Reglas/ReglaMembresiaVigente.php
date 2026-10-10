<?php

declare(strict_types=1);

namespace Rbac\Reglas;

use Rbac\Contratos\ReglaAutorizacion;
use Rbac\Dominio\ContextoAutorizacion;

/** D-07/R-02: el usuario debe tener al menos una membresía activa y dentro de su vigencia. */
final class ReglaMembresiaVigente implements ReglaAutorizacion
{
    public function evaluar(ContextoAutorizacion $contexto): ?string
    {
        return $contexto->membresiasVigentes() === [] ? 'MEMBRESIA_NO_VIGENTE' : null;
    }
}

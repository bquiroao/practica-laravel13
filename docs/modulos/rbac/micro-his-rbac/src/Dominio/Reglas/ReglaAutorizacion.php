<?php

declare(strict_types=1);

namespace Rbac\Dominio\Reglas;

use Rbac\Dominio\Modelo\ContextoAutorizacion;

/** Punto de extensión (OCP): null si la condición se cumple, o el código del motivo de denegación. */
interface ReglaAutorizacion
{
    public function evaluar(ContextoAutorizacion $contexto): ?string;
}

<?php

declare(strict_types=1);

namespace Rbac\Dominio\Reglas\Autorizacion;

use Rbac\Dominio\Modelo\ContextoAutorizacion;
use Rbac\Dominio\Puertos\CatalogoPermisos;
use Rbac\Dominio\Reglas\ReglaAutorizacion;

/** D-08: algún rol vigente del usuario debe conceder el permiso que exige la operación. */
final class ReglaRolConcedePermiso implements ReglaAutorizacion
{
    public function __construct(private readonly CatalogoPermisos $catalogo)
    {
    }

    public function evaluar(ContextoAutorizacion $contexto): ?string
    {
        foreach ($contexto->membresiasVigentes() as $membresia) {
            if ($this->catalogo->rolConcede($membresia->rol, $contexto->solicitud->permiso)) {
                return null;
            }
        }

        return 'PERMISO_NO_ASIGNADO';
    }
}

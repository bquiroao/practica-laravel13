<?php

declare(strict_types=1);

namespace Rbac\Reglas\Asignacion;

use Rbac\Contratos\CatalogoPermisos;
use Rbac\Contratos\ReglaAsignacion;
use Rbac\Dominio\ErrorAsignacion;
use Rbac\Dominio\SolicitudAsignacion;

/** D-03 / E-02: el rol debe existir en el catálogo. */
final class ReglaRolEnCatalogo implements ReglaAsignacion
{
    public function __construct(private readonly CatalogoPermisos $catalogo)
    {
    }

    public function evaluar(SolicitudAsignacion $solicitud): ?ErrorAsignacion
    {
        return $this->catalogo->existeRol($solicitud->rol) ? null : new ErrorAsignacion('ROL_INVALIDO', 422);
    }
}

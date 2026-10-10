<?php

declare(strict_types=1);

namespace Rbac\Dominio\Puertos;

use Rbac\Dominio\Excepciones\ErrorPersistencia;

/** Catálogo de roles y de los permisos que concede cada rol. */
interface CatalogoPermisos
{
    /** @throws ErrorPersistencia */
    public function existeRol(string $rol): bool;

    /** @throws ErrorPersistencia */
    public function rolConcede(string $rol, string $permiso): bool;
}

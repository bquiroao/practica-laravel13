<?php

declare(strict_types=1);

namespace Rbac\Contratos;

/** Catálogo de roles y permisos (en el HIS lo respalda Spatie Laravel Permission). */
interface CatalogoPermisos
{
    public function existeRol(string $rol): bool;

    public function rolConcede(string $rol, string $permiso): bool;
}

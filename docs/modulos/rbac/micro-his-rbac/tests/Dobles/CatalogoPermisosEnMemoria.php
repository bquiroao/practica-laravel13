<?php

declare(strict_types=1);

namespace Rbac\Pruebas\Dobles;

use Rbac\Dominio\Puertos\CatalogoPermisos;

final class CatalogoPermisosEnMemoria implements CatalogoPermisos
{
    /** @param array<string, list<string>> $permisosPorRol */
    public function __construct(private readonly array $permisosPorRol)
    {
    }

    public function existeRol(string $rol): bool
    {
        return array_key_exists($rol, $this->permisosPorRol);
    }

    public function rolConcede(string $rol, string $permiso): bool
    {
        return in_array($permiso, $this->permisosPorRol[$rol] ?? [], true);
    }
}

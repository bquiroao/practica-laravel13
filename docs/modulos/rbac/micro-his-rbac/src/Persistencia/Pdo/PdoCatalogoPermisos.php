<?php

declare(strict_types=1);

namespace Rbac\Persistencia\Pdo;

use Rbac\Dominio\Puertos\CatalogoPermisos;

final class PdoCatalogoPermisos extends RepositorioPdo implements CatalogoPermisos
{
    public function existeRol(string $rol): bool
    {
        return $this->ejecutar('SELECT 1 FROM roles WHERE codigo = :rol', ['rol' => $rol])->fetchColumn() !== false;
    }

    public function rolConcede(string $rol, string $permiso): bool
    {
        return $this->ejecutar(
            'SELECT 1 FROM rol_permiso WHERE rol_codigo = :rol AND permiso_codigo = :permiso',
            ['rol' => $rol, 'permiso' => $permiso],
        )->fetchColumn() !== false;
    }
}

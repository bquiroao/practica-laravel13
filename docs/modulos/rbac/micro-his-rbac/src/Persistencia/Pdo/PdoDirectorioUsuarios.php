<?php

declare(strict_types=1);

namespace Rbac\Persistencia\Pdo;

use Rbac\Dominio\Puertos\DirectorioUsuarios;

final class PdoDirectorioUsuarios extends RepositorioPdo implements DirectorioUsuarios
{
    public function estadoDe(string $usuarioId): ?string
    {
        $estado = $this->ejecutar('SELECT estado FROM usuarios WHERE usuario_id = :usuario', ['usuario' => $usuarioId])
            ->fetchColumn();

        return $estado === false ? null : (string) $estado;
    }
}

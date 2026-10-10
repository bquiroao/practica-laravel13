<?php

declare(strict_types=1);

namespace Rbac\Persistencia\EnMemoria;

use Rbac\Dominio\Puertos\DirectorioUsuarios;

final class DirectorioUsuariosEnMemoria implements DirectorioUsuarios
{
    /** @param array<string, string> $estados usuarioId => 'active' | 'inactive' */
    public function __construct(private readonly array $estados)
    {
    }

    public function estadoDe(string $usuarioId): ?string
    {
        return $this->estados[$usuarioId] ?? null;
    }
}

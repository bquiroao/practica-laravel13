<?php

declare(strict_types=1);

namespace Rbac\Contratos;

interface DirectorioUsuarios
{
    /** Estado del usuario ('active' o 'inactive'), o null si no existe. */
    public function estadoDe(string $usuarioId): ?string;
}

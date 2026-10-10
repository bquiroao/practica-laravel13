<?php

declare(strict_types=1);

namespace Rbac\Dominio\Puertos;

use Rbac\Dominio\Excepciones\ErrorPersistencia;

interface DirectorioUsuarios
{
    /**
     * Estado del usuario ('active' o 'inactive'), o null si no existe.
     *
     * @throws ErrorPersistencia
     */
    public function estadoDe(string $usuarioId): ?string;
}

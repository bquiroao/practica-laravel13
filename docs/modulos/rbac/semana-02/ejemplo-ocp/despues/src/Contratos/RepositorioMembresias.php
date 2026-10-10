<?php

declare(strict_types=1);

namespace Rbac\Contratos;

use Rbac\Dominio\Membresia;

interface RepositorioMembresias
{
    /** @return list<Membresia> */
    public function membresiasDe(string $usuarioId, string $hospitalId): array;

    public function existeActiva(string $usuarioId, string $hospitalId, string $rol): bool;

    public function guardar(Membresia $membresia): void;
}

<?php

declare(strict_types=1);

namespace Rbac\EnMemoria;

use Rbac\Contratos\RepositorioMembresias;
use Rbac\Dominio\Membresia;

final class RepositorioMembresiasEnMemoria implements RepositorioMembresias
{
    /** @param list<Membresia> $membresias */
    public function __construct(private array $membresias = [])
    {
    }

    public function membresiasDe(string $usuarioId, string $hospitalId): array
    {
        return array_values(array_filter(
            $this->membresias,
            static fn (Membresia $m): bool => $m->usuarioId === $usuarioId && $m->hospitalId === $hospitalId,
        ));
    }

    public function existeActiva(string $usuarioId, string $hospitalId, string $rol): bool
    {
        foreach ($this->membresiasDe($usuarioId, $hospitalId) as $m) {
            if ($m->rol === $rol && $m->estado === 'active' && $m->validoHasta === null) {
                return true;
            }
        }

        return false;
    }

    public function guardar(Membresia $membresia): void
    {
        $this->membresias[] = $membresia;
    }

    /** @return list<Membresia> */
    public function instantanea(): array
    {
        return $this->membresias;
    }

    /** @param list<Membresia> $membresias */
    public function restaurar(array $membresias): void
    {
        $this->membresias = $membresias;
    }

    public function total(): int
    {
        return count($this->membresias);
    }
}

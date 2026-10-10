<?php

declare(strict_types=1);

namespace Rbac\Pruebas\Dobles;

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Excepciones\MembresiaDuplicada;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Dominio\Puertos\RepositorioMembresias;

/** Doble del repositorio: imita el índice único de la base y puede simular fallas. */
final class RepositorioMembresiasEnMemoria implements RepositorioMembresias
{
    public bool $fallarLectura = false;
    public bool $fallarEscritura = false;
    /** Simula una carrera: la regla no ve el duplicado, pero la base sí lo rechaza al insertar. */
    public bool $ocultarDuplicados = false;

    /** @param list<Membresia> $membresias */
    public function __construct(private array $membresias = [])
    {
    }

    public function membresiasDe(string $usuarioId, string $hospitalId): array
    {
        if ($this->fallarLectura) {
            throw new ErrorPersistencia('Lectura simulada fallida', '08006');
        }

        return array_values(array_filter(
            $this->membresias,
            static fn (Membresia $m): bool => $m->usuarioId === $usuarioId && $m->hospitalId === $hospitalId,
        ));
    }

    public function existeActiva(string $usuarioId, string $hospitalId, string $rol): bool
    {
        if ($this->fallarLectura) {
            throw new ErrorPersistencia('Lectura simulada fallida', '08006');
        }

        return !$this->ocultarDuplicados && $this->hayActiva($usuarioId, $hospitalId, $rol);
    }

    public function guardar(Membresia $membresia): void
    {
        if ($this->fallarEscritura) {
            throw new ErrorPersistencia('Escritura simulada fallida', '08006');
        }
        if ($membresia->estado === 'active' && $membresia->validoHasta === null
            && $this->hayActiva($membresia->usuarioId, $membresia->hospitalId, $membresia->rol)) {
            throw new MembresiaDuplicada('Violación de índice único simulada', '23505');
        }
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

    private function hayActiva(string $usuarioId, string $hospitalId, string $rol): bool
    {
        foreach ($this->membresias as $m) {
            if ($m->usuarioId === $usuarioId && $m->hospitalId === $hospitalId && $m->rol === $rol
                && $m->estado === 'active' && $m->validoHasta === null) {
                return true;
            }
        }

        return false;
    }
}

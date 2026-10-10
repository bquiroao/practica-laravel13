<?php

declare(strict_types=1);

namespace Rbac\Persistencia\EnMemoria;

use Rbac\Dominio\Excepciones\MembresiaDuplicada;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Dominio\Puertos\RepositorioMembresias;

/** Adaptador InMemory: mismo contrato que el PDO, incluido el rechazo de duplicados del índice único. */
final class RepositorioMembresiasEnMemoria implements RepositorioMembresias, Restaurable
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
            if ($m->rol === $rol && self::esActivaAbierta($m)) {
                return true;
            }
        }

        return false;
    }

    public function guardar(Membresia $membresia): void
    {
        if (self::esActivaAbierta($membresia) && $this->existeActiva($membresia->usuarioId, $membresia->hospitalId, $membresia->rol)) {
            // Equivale a la violación del índice único parcial membresias_activa_abierta_unica (SQLSTATE 23505).
            throw new MembresiaDuplicada('Ya existe una membresía activa igual', '23505');
        }
        $this->membresias[] = $membresia;
    }

    public function instantanea(): mixed
    {
        return $this->membresias;
    }

    public function restaurar(mixed $instantanea): void
    {
        $this->membresias = $instantanea;
    }

    private static function esActivaAbierta(Membresia $m): bool
    {
        return $m->estado === 'active' && $m->validoHasta === null;
    }
}

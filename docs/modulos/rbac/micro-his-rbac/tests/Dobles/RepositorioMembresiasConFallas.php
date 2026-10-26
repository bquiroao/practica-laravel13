<?php

declare(strict_types=1);

namespace Rbac\Pruebas\Dobles;

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Dominio\Puertos\RepositorioMembresias;
use Rbac\Persistencia\EnMemoria\RepositorioMembresiasEnMemoria;

/** Decorador de prueba: reutiliza el adaptador InMemory real y le agrega fallas a pedido. */
final class RepositorioMembresiasConFallas implements RepositorioMembresias
{
    public bool $fallarLectura = false;
    public bool $fallarEscritura = false;
    /** Simula una carrera: la regla no ve el duplicado, pero el almacén lo rechaza al guardar. */
    public bool $ocultarDuplicados = false;

    public function __construct(private readonly RepositorioMembresiasEnMemoria $interno)
    {
    }

    public function membresiasDe(string $usuarioId, string $hospitalId): array
    {
        $this->fallarSi($this->fallarLectura);

        return $this->interno->membresiasDe($usuarioId, $hospitalId);
    }

    public function existeActiva(string $usuarioId, string $hospitalId, string $rol): bool
    {
        $this->fallarSi($this->fallarLectura);

        return !$this->ocultarDuplicados && $this->interno->existeActiva($usuarioId, $hospitalId, $rol);
    }

    public function guardar(Membresia $membresia): void
    {
        $this->fallarSi($this->fallarEscritura);
        $this->interno->guardar($membresia);
    }

    public function revocar(string $usuarioId, string $hospitalId, string $rol, \DateTimeImmutable $momento): int
    {
        $this->fallarSi($this->fallarEscritura);

        return $this->interno->revocar($usuarioId, $hospitalId, $rol, $momento);
    }

    public function total(): int
    {
        return count($this->interno->instantanea());
    }

    private function fallarSi(bool $condicion): void
    {
        if ($condicion) {
            throw new ErrorPersistencia('Falla simulada del almacén', '08006');
        }
    }
}

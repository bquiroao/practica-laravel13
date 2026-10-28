<?php

declare(strict_types=1);

namespace Rbac\Dominio\Puertos;

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Excepciones\MembresiaDuplicada;
use Rbac\Dominio\Modelo\Membresia;

interface RepositorioMembresias
{
    /**
     * @return list<Membresia>
     * @throws ErrorPersistencia
     */
    public function membresiasDe(string $usuarioId, string $hospitalId): array;

    /** @throws ErrorPersistencia */
    public function existeActiva(string $usuarioId, string $hospitalId, string $rol): bool;

    /**
     * @throws MembresiaDuplicada si ya existe una membresía activa abierta igual (R-03)
     * @throws ErrorPersistencia
     */
    public function guardar(Membresia $membresia): void;

    /**
     * Marca como 'revoked' las membresías activas y no vencidas del rol (semana 6).
     *
     * @return int cuántas membresías se revocaron
     * @throws ErrorPersistencia
     */
    public function revocar(string $usuarioId, string $hospitalId, string $rol, \DateTimeImmutable $momento): int;
}

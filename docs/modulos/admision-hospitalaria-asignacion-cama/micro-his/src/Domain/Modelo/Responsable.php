<?php
declare(strict_types=1);

namespace Admision\Domain\Modelo;

/** Quién hizo la operación y en qué solicitud (RN-05). */
final class Responsable
{
    public function __construct(
        public readonly string $usuarioId,
        public readonly Rol $rol,
        public readonly string $solicitudId,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Rbac\Dominio\Modelo;

/** Datos de una operación que debe autorizarse. */
final class SolicitudAutorizacion
{
    public function __construct(
        public readonly string $usuarioId,
        public readonly string $tenantToken,
        public readonly string $tenantSolicitud,
        public readonly string $permiso,
        public readonly string $recurso,
        public readonly string $ip,
        public readonly \DateTimeImmutable $ahora,
    ) {
    }
}

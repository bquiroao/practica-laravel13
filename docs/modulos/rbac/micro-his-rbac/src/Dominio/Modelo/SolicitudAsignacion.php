<?php

declare(strict_types=1);

namespace Rbac\Dominio\Modelo;

/** Pedido de un administrador para asignar un rol a un usuario en su hospital. */
final class SolicitudAsignacion
{
    public function __construct(
        public readonly string $adminId,
        public readonly string $hospitalId,
        public readonly string $usuarioDestinoId,
        public readonly string $rol,
        public readonly \DateTimeImmutable $validoDesde,
        public readonly ?\DateTimeImmutable $validoHasta,
        public readonly \DateTimeImmutable $ahora,
    ) {
    }
}

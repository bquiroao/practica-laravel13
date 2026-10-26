<?php

declare(strict_types=1);

namespace Rbac\Dominio\Modelo;

/** Pedido de un administrador para revocar un rol de un usuario en su hospital (semana 6). */
final class SolicitudRevocacion
{
    public function __construct(
        public readonly string $adminId,
        public readonly string $hospitalId,
        public readonly string $usuarioDestinoId,
        public readonly string $rol,
        public readonly string $motivo,
        public readonly \DateTimeImmutable $ahora,
    ) {
    }
}

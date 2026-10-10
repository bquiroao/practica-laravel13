<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\Comandos;

/** Entrada del caso de uso AsignarRol. */
final class ComandoAsignarRol
{
    public function __construct(
        public readonly string $adminId,
        public readonly string $tenantToken,
        public readonly string $hospitalId,
        public readonly string $usuarioDestinoId,
        public readonly string $rol,
        public readonly \DateTimeImmutable $validoDesde,
        public readonly ?\DateTimeImmutable $validoHasta,
        public readonly string $ip,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\Comandos;

/** Entrada del caso de uso RevocarRol (semana 6). */
final class ComandoRevocarRol
{
    public function __construct(
        public readonly string $adminId,
        public readonly string $tenantToken,
        public readonly string $hospitalId,
        public readonly string $usuarioDestinoId,
        public readonly string $rol,
        public readonly string $motivo,
        public readonly string $ip,
    ) {
    }
}

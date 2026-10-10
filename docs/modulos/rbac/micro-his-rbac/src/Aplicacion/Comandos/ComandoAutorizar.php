<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\Comandos;

/** Entrada del caso de uso AutorizarOperacion. */
final class ComandoAutorizar
{
    public function __construct(
        public readonly string $usuarioId,
        public readonly string $tenantToken,
        public readonly string $tenantSolicitud,
        public readonly string $permiso,
        public readonly string $recurso,
        public readonly string $ip,
    ) {
    }
}

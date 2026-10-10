<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http;

/** Quién hace la petición y a qué hospital pertenece su token. */
final class Identidad
{
    public function __construct(
        public readonly string $usuarioId,
        public readonly string $tenantToken,
    ) {
    }
}

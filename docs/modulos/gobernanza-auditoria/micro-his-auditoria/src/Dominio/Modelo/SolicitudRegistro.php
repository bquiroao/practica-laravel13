<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Modelo;

/** Lo que envía un módulo emisor, todavía sin validar. */
final class SolicitudRegistro
{
    /** @param array<string, mixed> $datos */
    public function __construct(
        public readonly string $emisor,
        public readonly ?string $hospitalToken,
        public readonly array $datos,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\Comandos;

/** Tipos primitivos: Presentación no necesita conocer el Dominio para invocar el caso de uso. */
final class ComandoRegistrar
{
    /** @param array<string, mixed> $datos */
    public function __construct(
        public readonly string $emisor,
        public readonly ?string $hospitalToken,
        public readonly array $datos,
    ) {
    }
}

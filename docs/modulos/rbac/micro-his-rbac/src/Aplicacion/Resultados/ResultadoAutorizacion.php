<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\Resultados;

/**
 * Salida de AutorizarOperacion hacia la Presentación.
 * No incluye el motivo de denegación: así la capa HTTP no puede revelarlo (R-08).
 */
final class ResultadoAutorizacion
{
    private function __construct(
        public readonly bool $permitido,
        public readonly int $codigoHttp,
        public readonly ?string $correlationId,
    ) {
    }

    public static function permitido(): self
    {
        return new self(true, 200, null);
    }

    public static function denegado(string $correlationId): self
    {
        return new self(false, 403, $correlationId);
    }
}

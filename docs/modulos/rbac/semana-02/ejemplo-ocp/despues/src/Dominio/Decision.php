<?php

declare(strict_types=1);

namespace Rbac\Dominio;

/** Resultado de autorizar: PERMITIDO (200) o DENEGADO (403 con correlation_id). */
final class Decision
{
    private function __construct(
        public readonly bool $permitido,
        public readonly int $codigoHttp,
        public readonly ?string $motivo,
        public readonly ?string $correlationId,
    ) {
    }

    public static function permitir(): self
    {
        return new self(true, 200, null, null);
    }

    public static function denegar(string $motivo, string $correlationId): self
    {
        return new self(false, 403, $motivo, $correlationId);
    }
}

<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http;

/** Quién llama, según el token. $hospital = '*' para el super administrador (toda la red). */
final class Identidad
{
    public function __construct(
        public readonly string $actor,
        public readonly string $hospital,
    ) {
    }

    public function puedeActuarEn(string $hospital): bool
    {
        return $this->hospital === '*' || $this->hospital === $hospital;
    }
}

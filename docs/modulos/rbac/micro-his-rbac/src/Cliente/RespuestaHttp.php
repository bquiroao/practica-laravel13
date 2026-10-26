<?php

declare(strict_types=1);

namespace Rbac\Cliente;

final class RespuestaHttp
{
    /** @param array<string, string> $cabeceras nombres en minúsculas */
    public function __construct(
        public readonly int $estado,
        public readonly string $cuerpo,
        public readonly array $cabeceras = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        $datos = json_decode($this->cuerpo, true);

        return is_array($datos) ? $datos : [];
    }
}

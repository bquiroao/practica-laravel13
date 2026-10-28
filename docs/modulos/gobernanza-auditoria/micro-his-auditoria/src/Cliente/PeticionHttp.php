<?php

declare(strict_types=1);

namespace Auditoria\Cliente;

final class PeticionHttp
{
    /**
     * @param array<string, string> $cabeceras
     * @param array<string, string> $consulta
     * @param array<string, mixed>|null $cuerpo
     */
    public function __construct(
        public readonly string $metodo,
        public readonly string $ruta,
        public readonly array $cabeceras = [],
        public readonly array $consulta = [],
        public readonly ?array $cuerpo = null,
    ) {
    }
}

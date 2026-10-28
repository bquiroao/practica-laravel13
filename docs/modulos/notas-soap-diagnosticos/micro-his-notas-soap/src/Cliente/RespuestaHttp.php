<?php

declare(strict_types=1);

namespace NotasSoap\Cliente;

final class RespuestaHttp
{
    /**
     * @param array<string, string> $cabeceras nombres en minúsculas
     * @param array<string, mixed>|null $cuerpo JSON decodificado
     */
    public function __construct(
        public readonly int $estado,
        public readonly array $cabeceras,
        public readonly ?array $cuerpo,
    ) {
    }

    public function cabecera(string $nombre): ?string
    {
        return $this->cabeceras[strtolower($nombre)] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http;

final class Respuesta
{
    /**
     * @param array<string, mixed> $cuerpo
     * @param array<string, string> $cabeceras
     */
    public function __construct(
        public readonly int $estado,
        public readonly array $cuerpo,
        public readonly array $cabeceras = [],
    ) {
    }

    /**
     * @param array<string, mixed> $cuerpo
     * @param array<string, string> $cabeceras
     */
    public static function json(int $estado, array $cuerpo, array $cabeceras = []): self
    {
        return new self($estado, $cuerpo, $cabeceras);
    }

    public static function error(int $estado, string $codigo): self
    {
        return new self($estado, ['error' => $codigo]);
    }

    public function enviar(): void
    {
        http_response_code($this->estado);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->cabeceras as $nombre => $valor) {
            header($nombre . ': ' . $valor);
        }
        echo json_encode($this->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

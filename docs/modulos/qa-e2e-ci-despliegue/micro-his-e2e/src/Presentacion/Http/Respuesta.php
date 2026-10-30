<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http;

final readonly class Respuesta
{
    /** @param array<string, mixed> $cuerpo */
    public function __construct(public int $estado, public array $cuerpo, public array $cabeceras = [])
    {
    }

    public function enviar(): void
    {
        http_response_code($this->estado);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->cabeceras as $k => $v) {
            header("$k: $v");
        }
        echo json_encode($this->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

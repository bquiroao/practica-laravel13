<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http;

final class Respuesta
{
    /** @param array<string, mixed> $cuerpo */
    public function __construct(public readonly int $estado, public readonly array $cuerpo)
    {
    }

    /** @param array<string, mixed> $cuerpo */
    public static function json(int $estado, array $cuerpo): self
    {
        return new self($estado, $cuerpo);
    }

    public static function error(int $estado, string $codigo, string $mensaje): self
    {
        return new self($estado, ['error' => ['code' => $codigo, 'message' => $mensaje]]);
    }

    public function enviar(): void
    {
        http_response_code($this->estado);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->cuerpo, JSON_UNESCAPED_UNICODE);
    }
}

<?php
declare(strict_types=1);

namespace Lab\Presentation\Api;

final class RespuestaJson
{
    public function __construct(
        public readonly int $estado,
        public readonly array $cuerpo,
        public readonly string $requestId,
    ) {
    }

    public function enviar(): void
    {
        http_response_code($this->estado);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Request-Id: ' . $this->requestId);
        echo json_encode($this->cuerpo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

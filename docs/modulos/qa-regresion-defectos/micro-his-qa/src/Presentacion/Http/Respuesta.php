<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http;

final readonly class Respuesta
{
    /**
     * @param array<string, mixed>  $cuerpo
     * @param array<string, string> $cabeceras
     */
    public function __construct(public int $estado, public array $cuerpo, public array $cabeceras = [])
    {
    }

    /** @param array<string, mixed> $cuerpo @param array<string, string> $cabeceras */
    public static function json(int $estado, array $cuerpo, array $cabeceras = []): self
    {
        return new self($estado, $cuerpo, $cabeceras);
    }

    public function conCabecera(string $nombre, string $valor): self
    {
        return new self($this->estado, $this->cuerpo, [$nombre => $valor] + $this->cabeceras);
    }

    /** Frases de estado explícitas: el servidor embebido de PHP no conoce la del 422 y enviaría «Unknown Status Code». */
    public const FRASES = [200 => 'OK', 201 => 'Created', 400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found',
        405 => 'Method Not Allowed', 409 => 'Conflict', 413 => 'Content Too Large', 422 => 'Unprocessable Content', 500 => 'Internal Server Error', 503 => 'Service Unavailable'];

    public function lineaDeEstado(): string
    {
        return sprintf('HTTP/1.1 %d %s', $this->estado, self::FRASES[$this->estado] ?? 'Status');
    }

    public function enviar(): void
    {
        header($this->lineaDeEstado(), true, $this->estado);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        foreach ($this->cabeceras as $nombre => $valor) {
            header("$nombre: $valor");
        }
        echo json_encode($this->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}

<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http;

/** Respuesta HTTP. Su contenido lo deciden las Vistas (ARQ-08). */
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

    /** Copia con una cabecera más (la usa el Kernel para X-Request-ID). */
    public function conCabecera(string $nombre, string $valor): self
    {
        return new self($this->estado, $this->cuerpo, [$nombre => $valor] + $this->cabeceras);
    }

    public function enviar(): void
    {
        http_response_code($this->estado);
        foreach ($this->cabeceras as $nombre => $valor) {
            header($nombre . ': ' . $valor);
        }
        if ($this->estado === 304) {
            return; // 304 Not Modified no lleva cuerpo
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

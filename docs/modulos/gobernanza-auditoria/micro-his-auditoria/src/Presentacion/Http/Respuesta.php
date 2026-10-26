<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http;

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

    public static function json(int $estado, array $cuerpo, array $cabeceras = []): self
    {
        return new self($estado, $cuerpo, $cabeceras + ['Content-Type' => 'application/json; charset=utf-8']);
    }

    /** La misma respuesta con una cabecera más (inmutable). */
    public function conCabecera(string $nombre, string $valor): self
    {
        return new self($this->estado, $this->cuerpo, [$nombre => $valor] + $this->cabeceras);
    }

    public function enviar(): void
    {
        foreach ($this->cabeceras as $nombre => $valor) {
            header("$nombre: $valor");
        }
        // Después de las cabeceras: header('Location: …') cambia el estado a 302 si se fija antes.
        http_response_code($this->estado);
        echo json_encode($this->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

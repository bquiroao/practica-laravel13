<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Web;

final class Respuesta
{
    /** @param array<string, string> $cabeceras */
    public function __construct(
        public readonly int $estado,
        public readonly string $cuerpo,
        public readonly array $cabeceras = ['Content-Type' => 'text/html; charset=UTF-8'],
    ) {
    }

    public static function redirigir(string $ruta): self
    {
        return new self(303, '', ['Location' => $ruta]);
    }

    public function conCabecera(string $nombre, string $valor): self
    {
        return new self($this->estado, $this->cuerpo, [$nombre => $valor] + $this->cabeceras);
    }

    public function enviar(): void
    {
        http_response_code($this->estado);
        foreach ($this->cabeceras as $nombre => $valor) {
            header("$nombre: $valor");
        }
        echo $this->cuerpo;
    }
}

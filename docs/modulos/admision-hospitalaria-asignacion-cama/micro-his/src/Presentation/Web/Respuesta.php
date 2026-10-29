<?php
declare(strict_types=1);

namespace Admision\Presentation\Web;

final class Respuesta
{
    /** @param array<string, string> $cabeceras */
    public function __construct(
        public readonly int $estado,
        public readonly string $cuerpo,
        public readonly array $cabeceras = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {
    }

    public function conCabecera(string $nombre, string $valor): self
    {
        return new self($this->estado, $this->cuerpo, [...$this->cabeceras, $nombre => $valor]);
    }

    public function enviar(): void
    {
        http_response_code($this->estado);
        foreach ($this->cabeceras as $nombre => $valor) {
            header("{$nombre}: {$valor}");
        }
        echo $this->cuerpo;
    }
}

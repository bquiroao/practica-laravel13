<?php
declare(strict_types=1);

namespace AlertasCriticas\Presentation\Web;

final class Respuesta
{
    public function __construct(
        public readonly int $estado,
        public readonly string $cuerpo = '',
        public readonly array $cabeceras = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {}

    /** 303: después de un POST el navegador vuelve con GET (no se reenvía el formulario al recargar). */
    public static function redirigir(string $destino, array $cabecerasExtra = []): self
    {
        return new self(303, '', ['Location' => $destino] + $cabecerasExtra);
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

<?php
declare(strict_types=1);

namespace MicroHis\Presentation\Http;

/** Respuesta HTTP reutilizable (HTML hoy, JSON en la semana 5). */
final class Respuesta
{
    /** @param array<string,string> $cabeceras */
    public function __construct(
        public readonly int $codigo,
        public readonly string $cuerpo,
        public readonly array $cabeceras = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {
    }

    public function enviar(): void
    {
        http_response_code($this->codigo);
        foreach ($this->cabeceras as $nombre => $valor) {
            header("{$nombre}: {$valor}");
        }
        echo $this->cuerpo;
    }
}

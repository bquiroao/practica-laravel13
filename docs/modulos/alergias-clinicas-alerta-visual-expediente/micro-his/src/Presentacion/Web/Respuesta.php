<?php
declare(strict_types=1);

namespace Alergias\Presentacion\Web;

final class Respuesta
{
    /** @param array<string, string> $cabeceras */
    public function __construct(
        public readonly int $estado,
        public readonly string $cuerpo,
        public readonly array $cabeceras = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {
    }

    public static function redirigir(string $ruta): self
    {
        return new self(303, '', ['Location' => $ruta]);
    }
}

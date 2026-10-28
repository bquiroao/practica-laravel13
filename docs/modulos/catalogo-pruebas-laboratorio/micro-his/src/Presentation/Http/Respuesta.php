<?php
declare(strict_types=1);

namespace Lab\Presentation\Http;

/** Lo que el controlador decide: estado HTTP, vista y datos para la vista. */
final class Respuesta
{
    public function __construct(
        public readonly int $estado,
        public readonly string $vista,
        public readonly array $datos = [],
    ) {
    }
}

<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Api;

/** Quién llama, con qué rol y en qué hospital (tenant). Sale SIEMPRE del token, nunca del cuerpo. */
final class Identidad
{
    public function __construct(
        public readonly string $usuario,
        public readonly string $rol,
        public readonly string $hospitalId,
    ) {
    }
}

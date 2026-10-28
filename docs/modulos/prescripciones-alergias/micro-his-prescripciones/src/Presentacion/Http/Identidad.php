<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http;

/** Quién llama y en qué hospital, según el token (nunca según el cuerpo). */
final readonly class Identidad
{
    public function __construct(public string $usuario, public string $hospital)
    {
    }
}

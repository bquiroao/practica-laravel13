<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http;

/** Quién llama y en qué tenant, según el token (nunca según el cuerpo). */
final readonly class Identidad
{
    public function __construct(public string $usuario, public string $tenant)
    {
    }
}

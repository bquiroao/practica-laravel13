<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http;

/** Quién llama y en qué tenant: sale del token, nunca del cuerpo. */
final readonly class Identidad
{
    public function __construct(public string $usuario, public string $tenant)
    {
    }
}

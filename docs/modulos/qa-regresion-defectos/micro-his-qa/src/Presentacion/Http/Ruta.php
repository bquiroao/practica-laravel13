<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http;

final readonly class Ruta
{
    /** @param \Closure(Solicitud, Identidad, array<string, string>): Respuesta $manejador */
    public function __construct(public string $metodo, public string $patron, public \Closure $manejador)
    {
    }

    /** @return array<string, string>|null parámetros con nombre si la ruta coincide */
    public function coincide(string $ruta): ?array
    {
        if (!preg_match($this->patron, $ruta, $m)) {
            return null;
        }

        return array_filter($m, static fn ($k): bool => is_string($k), ARRAY_FILTER_USE_KEY);
    }
}

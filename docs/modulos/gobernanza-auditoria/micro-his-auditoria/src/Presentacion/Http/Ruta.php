<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http;

final class Ruta
{
    /** @param \Closure(Solicitud, Identidad, array<string, string>): Respuesta $accion */
    public function __construct(
        public readonly string $metodo,
        public readonly string $patron,
        public readonly \Closure $accion,
    ) {
    }

    /** @return array<string, string>|null los parámetros con nombre de la ruta, o null si no coincide */
    public function coincide(string $ruta): ?array
    {
        if (preg_match($this->patron, $ruta, $m) !== 1) {
            return null;
        }

        return array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}

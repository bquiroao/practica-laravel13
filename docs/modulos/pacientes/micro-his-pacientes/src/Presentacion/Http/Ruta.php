<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http;

/** Una ruta: método, patrón y el método del controlador que la atiende. */
final class Ruta
{
    /** @param \Closure(Solicitud, Identidad, array<string, string>): Respuesta $manejador */
    public function __construct(
        public readonly string $metodo,
        public readonly string $patron,
        public readonly \Closure $manejador,
    ) {
    }

    /** @return array<string, string>|null parámetros capturados, o null si no coincide */
    public function coincide(string $ruta): ?array
    {
        if (preg_match($this->patron, $ruta, $coincidencias) !== 1) {
            return null;
        }

        return array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}

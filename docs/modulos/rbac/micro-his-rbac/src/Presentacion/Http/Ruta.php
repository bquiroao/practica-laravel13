<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http;

/** Una ruta protegida: método, patrón, permiso requerido y método de controlador que la atiende. */
final class Ruta
{
    /**
     * @param string|null $permiso permiso que exige la ruta; null = solo autenticación
     *                             (reservado al endpoint de decisión, que ES la autorización; ver ARQ-08)
     * @param \Closure(Peticion): Respuesta $destino método de controlador, p. ej. $controlador->crear(...)
     */
    public function __construct(
        public readonly string $metodo,
        public readonly string $patron,
        public readonly ?string $permiso,
        public readonly \Closure $destino,
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

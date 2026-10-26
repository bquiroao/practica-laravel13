<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http;

/**
 * Una operación de la API: método, plantilla de ruta y permiso requerido.
 * La plantilla usa {nombre} para los parámetros (igual que en api/openapi.yaml).
 * La prueba API-01 compara esta tabla con el contrato: no pueden divergir.
 */
final class Ruta
{
    /** @param \Closure(Solicitud, Identidad, string, array<string, string>): Respuesta $accion */
    public function __construct(
        public readonly string $metodo,
        public readonly string $plantilla,
        public readonly string $permiso,
        public readonly \Closure $accion,
    ) {
    }

    /** @return array<string, string>|null parámetros si la ruta coincide; null si no */
    public function coincide(string $ruta): ?array
    {
        $patron = '#^' . preg_replace('/\\\\\{(\w+)\\\\\}/', '(?<$1>[A-Za-z0-9-]{1,64})', preg_quote($this->plantilla, '#')) . '$#';
        if (preg_match($patron, $ruta, $m) !== 1) {
            return null;
        }

        return array_filter($m, static fn ($k): bool => is_string($k), ARRAY_FILTER_USE_KEY);
    }
}

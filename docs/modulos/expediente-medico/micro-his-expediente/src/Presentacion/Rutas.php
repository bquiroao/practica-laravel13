<?php
declare(strict_types=1);

namespace MicroHis\Expediente\Presentacion;

/** Tabla de rutas (entrada MVC): método y ruta HTTP -> [acción del controlador ('' si no existe), parámetros de la ruta]. */
final class Rutas
{
    public static function resolver(string $metodo, string $ruta): array
    {
        if (!preg_match('#^/patients/([^/]+)/record(/external)?$#', $ruta, $m)) return ['', []];
        $externa = isset($m[2]);
        return match (true) {
            $metodo === 'POST' && !$externa => ['abrir', ['paciente' => $m[1]]],
            $metodo === 'GET' => ['consultar', ['paciente' => $m[1], 'longitudinal' => $externa]],
            default => ['', []],
        };
    }
}

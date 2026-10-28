<?php
declare(strict_types=1);

namespace MicroHis\DashboardOcupacion\Presentacion;

/** Tabla de rutas (entrada MVC): método y ruta HTTP -> [acción del controlador ('' si no existe), parámetros de la ruta]. */
final class Rutas
{
    public static function resolver(string $metodo, string $ruta): array
    {
        return match (true) {
            $metodo === 'POST' && $ruta === '/ocupacion/cortes' => ['consolidar', []],
            $metodo === 'GET' && $ruta === '/ocupacion' => ['consultar', []],
            default => ['', []],
        };
    }
}

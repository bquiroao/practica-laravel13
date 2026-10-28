<?php
declare(strict_types=1);

namespace MicroHis\DashboardBase\Presentacion;

/** Tabla de rutas (entrada MVC): método y ruta HTTP -> [acción del controlador ('' si no existe), parámetros de la ruta]. */
final class Rutas
{
    public static function resolver(string $metodo, string $ruta): array
    {
        return match (true) {
            $metodo === 'GET' && $ruta === '/dashboard' => ['inicio', []],
            $metodo === 'GET' && preg_match('#^/dashboard/tarjetas/([a-z_]+)$#', $ruta, $m) === 1 => ['tarjeta', ['id' => $m[1]]],
            default => ['', []],
        };
    }
}

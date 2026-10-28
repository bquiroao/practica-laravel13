<?php
declare(strict_types=1);

namespace MicroHis\Citas\Presentacion;

/** Tabla de rutas (entrada MVC): método y ruta HTTP -> acción del controlador y parámetros tomados de la ruta. */
final class Rutas
{
    /** @return array{0: string, 1: array<string, int>} acción ('' si la ruta no existe) y parámetros */
    public static function resolver(string $metodo, string $ruta): array
    {
        return match (true) {
            $metodo === 'POST' && $ruta === '/citas' => ['solicitar', []],
            $metodo === 'POST' && preg_match('#^/citas/(\d+)/confirmacion$#', $ruta, $m) === 1 => ['confirmar', ['id' => (int) $m[1]]],
            $metodo === 'POST' && preg_match('#^/citas/(\d+)/cancelacion$#', $ruta, $m) === 1 => ['cancelar', ['id' => (int) $m[1]]],
            default => ['', []],
        };
    }
}

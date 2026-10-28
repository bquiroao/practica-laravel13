<?php
declare(strict_types=1);

namespace MicroHis\Catalogo\Presentacion;

/** Tabla de rutas (entrada MVC): método y ruta HTTP -> acción del controlador y parámetros tomados de la ruta. */
final class Rutas
{
    /** @return array{0: string, 1: array<string, string>} acción ('' si la ruta no existe) y parámetros */
    public static function resolver(string $metodo, string $ruta): array
    {
        return match (true) {
            $metodo === 'POST' && $ruta === '/salas' => ['registrarSala', []],
            $metodo === 'POST' && preg_match('#^/salas/([^/]+)/camas$#', $ruta, $m) === 1 => ['registrarCama', ['sala' => $m[1]]],
            $metodo === 'PATCH' && preg_match('#^/salas/([^/]+)/camas/([^/]+)/estado$#', $ruta, $m) === 1 => ['cambiarEstado', ['sala' => $m[1], 'cama' => $m[2]]],
            default => ['', []],
        };
    }
}

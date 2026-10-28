<?php
declare(strict_types=1);

namespace MicroHis\ContratosApi\Presentacion;

/** Tabla de rutas (entrada MVC): método y ruta HTTP -> [acción del controlador ('' si no existe), parámetros de la ruta]. */
final class Rutas
{
    private const REVISION = ['aprobacion' => 'aprobar', 'rechazo' => 'rechazar', 'obsolescencia' => 'obsoleta'];

    public static function resolver(string $metodo, string $ruta): array
    {
        return match (true) {
            $metodo === 'POST' && preg_match('#^/documentos/([a-z0-9-]+)/versiones$#', $ruta, $m) === 1 => ['enviar', ['slug' => $m[1]]],
            $metodo === 'POST' && preg_match('#^/versiones/(\d+)/(aprobacion|rechazo|obsolescencia)$#', $ruta, $m) === 1 => [self::REVISION[$m[2]], ['id' => (int) $m[1]]],
            $metodo === 'GET' && preg_match('#^/documentos/([a-z0-9-]+)(?:/versiones/(\d+\.\d+\.\d+))?$#', $ruta, $m) === 1
                => ['consultar', ['slug' => $m[1], 'version' => ($m[2] ?? '') ?: null]],
            default => ['', []],
        };
    }
}

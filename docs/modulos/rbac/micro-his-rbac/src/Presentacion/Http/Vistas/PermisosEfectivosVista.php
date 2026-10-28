<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Vistas;

use Rbac\Presentacion\Http\Respuesta;

/** Vista de los permisos efectivos: un mapa permiso → booleano. Sin motivos ni referencias (no hay evento). */
final class PermisosEfectivosVista
{
    /** @param array<string, bool> $permisos */
    public static function mapa(array $permisos): Respuesta
    {
        return Respuesta::json(200, ['permisos' => $permisos]);
    }
}

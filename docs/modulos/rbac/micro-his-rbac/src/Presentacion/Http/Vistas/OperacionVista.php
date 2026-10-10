<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Vistas;

use Rbac\Presentacion\Http\Respuesta;

/** Vista de una operación clínica de demostración. */
final class OperacionVista
{
    public static function registrada(string $descripcion): Respuesta
    {
        return Respuesta::json(201, ['mensaje' => $descripcion . ' registrada (demo).']);
    }
}

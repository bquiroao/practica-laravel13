<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Vistas;

use Rbac\Aplicacion\Resultados\ResultadoAsignacion;
use Rbac\Presentacion\Http\Respuesta;

/** Vista del resultado de una asignación de rol. */
final class AsignacionVista
{
    public static function resultado(ResultadoAsignacion $resultado): Respuesta
    {
        return match ($resultado->codigoHttp) {
            201 => Respuesta::json(201, ['codigo' => $resultado->codigo, 'correlation_id' => $resultado->correlationId]),
            403 => ErrorVista::accesoDenegado($resultado->correlationId),
            500 => Respuesta::json(500, ['mensaje' => 'No se pudo completar la operación.']),
            default => Respuesta::json($resultado->codigoHttp, ['codigo' => $resultado->codigo]),
        };
    }
}

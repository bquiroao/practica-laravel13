<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Vistas;

use Rbac\Aplicacion\Resultados\ResultadoRevocacion;
use Rbac\Presentacion\Http\Respuesta;

/** Vista del resultado de una revocación de rol. */
final class RevocacionVista
{
    public static function resultado(ResultadoRevocacion $resultado): Respuesta
    {
        return match ($resultado->codigoHttp) {
            200 => Respuesta::json(200, ['codigo' => $resultado->codigo, 'correlation_id' => $resultado->correlationId]),
            403 => ErrorVista::accesoDenegado($resultado->correlationId),
            500 => Respuesta::json(500, ['mensaje' => 'No se pudo completar la operación.']),
            default => Respuesta::json($resultado->codigoHttp, ['codigo' => $resultado->codigo]),
        };
    }
}

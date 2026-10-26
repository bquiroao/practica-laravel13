<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http\Vistas;

use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Presentacion\Http\Respuesta;

/** MVC — Vista de los resultados exitosos; si el Resultado es un error, delega en ErrorVista. */
final class MovimientosVista
{
    private const RUTA = '/api/v1/auditoria/movimientos/';

    public static function registro(Resultado $r): Respuesta
    {
        if ($r->estado === Resultado::CREADO) {
            return Respuesta::json(201, $r->datos, ['Location' => self::RUTA . $r->datos['id']]);
        }
        // El reintento (200) va SIN Location: PHP convertiría la respuesta en 302 (hallazgo H-2 de la semana 3).
        return $r->estado === Resultado::OK ? Respuesta::json(200, ['codigo' => $r->codigo] + $r->datos) : ErrorVista::deResultado($r);
    }

    public static function lista(Resultado $r): Respuesta
    {
        return $r->estado === Resultado::OK ? Respuesta::json(200, $r->datos) : ErrorVista::deResultado($r);
    }

    public static function detalle(Resultado $r): Respuesta
    {
        return $r->estado === Resultado::OK ? Respuesta::json(200, $r->datos) : ErrorVista::deResultado($r);
    }

    public static function verificacion(Resultado $r): Respuesta
    {
        return $r->estado === Resultado::OK ? Respuesta::json(200, $r->datos) : ErrorVista::deResultado($r);
    }
}

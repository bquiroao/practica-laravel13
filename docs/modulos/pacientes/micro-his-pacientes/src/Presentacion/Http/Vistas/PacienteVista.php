<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Vistas;

use Pacientes\Aplicacion\Resultados\Resultado;
use Pacientes\Presentacion\Http\Respuesta;

/** V de MVC: el formato de las respuestas exitosas. Si el resultado es un error, delega en ErrorVista. */
final class PacienteVista
{
    public static function registrado(Resultado $resultado): Respuesta
    {
        if (!$resultado->exitoso()) {
            return ErrorVista::deResultado($resultado);
        }

        return Respuesta::json(201, $resultado->datos, [
            'Location' => '/api/v1/pacientes/' . $resultado->datos['codigo'],
            'ETag' => self::etag($resultado->datos['version']),
        ]);
    }

    public static function lista(Resultado $resultado): Respuesta
    {
        return $resultado->exitoso() ? Respuesta::json(200, $resultado->datos) : ErrorVista::deResultado($resultado);
    }

    public static function detalle(Resultado $resultado): Respuesta
    {
        if (!$resultado->exitoso()) {
            return ErrorVista::deResultado($resultado);
        }

        return Respuesta::json(200, $resultado->datos, ['ETag' => self::etag($resultado->datos['paciente']['version'])]);
    }

    public static function actualizado(Resultado $resultado): Respuesta
    {
        if (!$resultado->exitoso()) {
            return ErrorVista::deResultado($resultado);
        }

        return Respuesta::json(200, $resultado->datos, ['ETag' => self::etag($resultado->datos['version'])]);
    }

    /** La versión viaja como ETag para devolverla luego en If-Match (RFC 9110). */
    private static function etag(int $version): string
    {
        return '"' . $version . '"';
    }
}

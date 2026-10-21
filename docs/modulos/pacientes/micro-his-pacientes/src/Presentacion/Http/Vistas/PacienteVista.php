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
        $datos = $resultado->datos;
        $cabeceras = [
            'Location' => '/api/v1/pacientes/' . $datos['codigo'],
            'ETag' => self::etag($datos['version']),
        ];
        // Reintento con la misma Idempotency-Key: el mismo cuerpo, marcado en una cabecera.
        if (($datos['repetida'] ?? false) === true) {
            $cabeceras['Idempotent-Replayed'] = 'true';
        }
        unset($datos['repetida']);

        return Respuesta::json(201, $datos, $cabeceras);
    }

    public static function lista(Resultado $resultado): Respuesta
    {
        return $resultado->exitoso() ? Respuesta::json(200, $resultado->datos) : ErrorVista::deResultado($resultado);
    }

    /** Con If-None-Match igual a la versión vigente responde 304 sin cuerpo (RFC 9110 §13.1.2). */
    public static function detalle(Resultado $resultado, string $ifNoneMatch = ''): Respuesta
    {
        if (!$resultado->exitoso()) {
            return ErrorVista::deResultado($resultado);
        }
        $etag = self::etag($resultado->datos['paciente']['version']);
        if ($ifNoneMatch !== '' && in_array($etag, self::etiquetas($ifNoneMatch), true)) {
            return Respuesta::json(304, [], ['ETag' => $etag]);
        }

        return Respuesta::json(200, $resultado->datos, ['ETag' => $etag]);
    }

    public static function actualizado(Resultado $resultado): Respuesta
    {
        if (!$resultado->exitoso()) {
            return ErrorVista::deResultado($resultado);
        }

        return Respuesta::json(200, $resultado->datos, ['ETag' => self::etag($resultado->datos['version'])]);
    }

    /** La versión viaja como ETag para devolverla luego en If-Match / If-None-Match. */
    private static function etag(int $version): string
    {
        return '"' . $version . '"';
    }

    /** @return list<string> etiquetas de If-None-Match, sin el prefijo débil W/ */
    private static function etiquetas(string $cabecera): array
    {
        return array_map(static fn (string $e): string => preg_replace('#^W/#', '', trim($e)) ?? '', explode(',', $cabecera));
    }
}

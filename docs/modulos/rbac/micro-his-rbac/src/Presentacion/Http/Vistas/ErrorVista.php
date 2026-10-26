<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Vistas;

use Rbac\Presentacion\Http\Respuesta;

/** Vista de errores: el único lugar que define el formato y los textos de las respuestas de error. */
final class ErrorVista
{
    public static function sinTenant(): Respuesta
    {
        return Respuesta::json(400, ['mensaje' => 'La cabecera X-Tenant-ID es obligatoria.']);
    }

    public static function noAutenticado(): Respuesta
    {
        return Respuesta::json(401, ['mensaje' => 'Token inválido o expirado.']);
    }

    /** R-08: solo un mensaje genérico y la referencia; nunca el motivo ni el rol. */
    public static function accesoDenegado(?string $correlationId): Respuesta
    {
        return Respuesta::json(403, ['mensaje' => 'Acceso denegado.', 'correlation_id' => $correlationId]);
    }

    public static function rutaNoEncontrada(): Respuesta
    {
        return Respuesta::json(404, ['mensaje' => 'Ruta no encontrada.']);
    }

    public static function recursoNoEncontrado(string $mensaje): Respuesta
    {
        return Respuesta::json(404, ['mensaje' => $mensaje]);
    }

    public static function metodoNoPermitido(): Respuesta
    {
        return Respuesta::json(405, ['mensaje' => 'Método no permitido.']);
    }

    /** @param list<string> $campos */
    public static function datosInvalidos(array $campos): Respuesta
    {
        return Respuesta::json(422, ['codigo' => 'DATOS_INVALIDOS', 'campos' => $campos]);
    }

    public static function errorInterno(): Respuesta
    {
        return Respuesta::json(500, ['mensaje' => 'Error interno.']);
    }

    public static function servicioNoDisponible(): Respuesta
    {
        return Respuesta::json(503, ['mensaje' => 'Servicio no disponible: no hay conexión con la base de datos.']);
    }
}

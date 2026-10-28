<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http\Vistas;

use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Presentacion\Http\Formularios\EntradaInvalida;
use Auditoria\Presentacion\Http\Respuesta;

/** MVC — Vista de los errores. Junto con MovimientosVista, el ÚNICO lugar con códigos HTTP. */
final class ErrorVista
{
    /** Categoría del Resultado (Aplicación) → código HTTP. */
    private const POR_CATEGORIA = [
        Resultado::INVALIDO => 422,
        Resultado::PROHIBIDO => 403,
        Resultado::CONFLICTO => 409,
        Resultado::NO_ENCONTRADO => 404,
        Resultado::NO_DISPONIBLE => 503,
    ];

    public static function deResultado(Resultado $resultado): Respuesta
    {
        return Respuesta::json(self::POR_CATEGORIA[$resultado->estado] ?? 500, ['error' => $resultado->codigo] + $resultado->datos);
    }

    public static function entradaInvalida(EntradaInvalida $e): Respuesta
    {
        return Respuesta::json(400, ['error' => $e->tipo] + ($e->campos === [] ? [] : ['campos' => $e->campos]));
    }

    public static function hospitalRequerido(): Respuesta
    {
        return Respuesta::json(400, ['error' => 'HOSPITAL_REQUERIDO']);
    }

    public static function noAutenticado(): Respuesta
    {
        return Respuesta::json(401, ['error' => 'NO_AUTENTICADO']);
    }

    public static function tenantNoCoincide(): Respuesta
    {
        return Respuesta::json(403, ['error' => 'TENANT_NO_COINCIDE']);
    }

    public static function rutaNoEncontrada(): Respuesta
    {
        return Respuesta::json(404, ['error' => 'RUTA_NO_ENCONTRADA']);
    }

    public static function metodoNoPermitido(): Respuesta
    {
        return Respuesta::json(405, ['error' => 'METODO_NO_PERMITIDO']);
    }

    /** Nada de trazas ni mensajes internos hacia el cliente. */
    public static function errorInterno(): Respuesta
    {
        return Respuesta::json(500, ['error' => 'ERROR_INTERNO']);
    }

    public static function servicioNoDisponible(): Respuesta
    {
        return Respuesta::json(503, ['error' => 'SERVICIO_NO_DISPONIBLE']);
    }
}

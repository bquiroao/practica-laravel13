<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Vistas;

use Pacientes\Aplicacion\Resultados\Resultado;
use Pacientes\Presentacion\Http\Formularios\EntradaInvalida;
use Pacientes\Presentacion\Http\Respuesta;

/** V de MVC: el ÚNICO lugar donde un error se convierte en código HTTP y cuerpo. */
final class ErrorVista
{
    /** Categoría del Resultado (Aplicación/Dominio) → código HTTP. */
    private const POR_CATEGORIA = [
        'invalido' => 422,
        'conflicto' => 409,
        'no_encontrado' => 404,
        'prohibido' => 403,
        Resultado::NO_DISPONIBLE => 503,
    ];

    private const POR_ENTRADA = [
        EntradaInvalida::CUERPO => 400,
        EntradaInvalida::FORMATO => 400,
        EntradaInvalida::VERSION_INVALIDA => 400,
        EntradaInvalida::VERSION_REQUERIDA => 428,
    ];

    public static function deResultado(Resultado $resultado): Respuesta
    {
        return Respuesta::json(self::POR_CATEGORIA[$resultado->estado] ?? 500, ['error' => $resultado->error] + $resultado->datos);
    }

    public static function entradaInvalida(EntradaInvalida $e): Respuesta
    {
        return Respuesta::json(self::POR_ENTRADA[$e->tipo], ['error' => $e->tipo] + ($e->campos === [] ? [] : ['campos' => $e->campos]));
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

    public static function servicioNoDisponible(string $codigo = 'SERVICIO_NO_DISPONIBLE'): Respuesta
    {
        return Respuesta::json(503, ['error' => $codigo]);
    }
}

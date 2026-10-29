<?php
declare(strict_types=1);

namespace Admision\Presentation;

/** Código de error estable -> estado HTTP. Lo comparten la web (semana 4) y la API (semana 5). */
final class MapaErrores
{
    private const ESTADOS = [
        'VALIDACION' => 422,
        'ACCESO_DENEGADO' => 403,
        'PACIENTE_NO_ENCONTRADO' => 404,
        'CAMA_NO_ENCONTRADA' => 404,
        'CAMA_NO_ELEGIBLE' => 422,
        'PACIENTE_CON_ADMISION_ACTIVA' => 409,
        'CAMA_YA_ASIGNADA' => 409,
        'CONFLICTO_CONCURRENCIA' => 409,
        'ERROR_PERSISTENCIA' => 503,
        'ADMISION_NO_ENCONTRADA' => 404,
        'NO_AUTENTICADO' => 401,
        'SOLICITUD_INVALIDA' => 400,
        'RECURSO_NO_ENCONTRADO' => 404,
        'METODO_NO_PERMITIDO' => 405,
    ];

    public static function estado(string $codigo): int
    {
        return self::ESTADOS[$codigo] ?? 500;
    }
}

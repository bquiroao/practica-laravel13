<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http;

use Auditoria\Aplicacion\Resultados\Resultado;

/** El ÚNICO lugar que convierte categorías de negocio en códigos HTTP. */
final class TraductorResultado
{
    private const ESTADOS = [
        Resultado::CREADO => 201,
        Resultado::OK => 200,
        Resultado::INVALIDO => 422,
        Resultado::PROHIBIDO => 403,
        Resultado::CONFLICTO => 409,
        Resultado::NO_ENCONTRADO => 404,
        Resultado::NO_DISPONIBLE => 503,
    ];

    public static function respuesta(Resultado $resultado, array $cabeceras = []): Respuesta
    {
        $estado = self::ESTADOS[$resultado->estado] ?? 500;
        if ($estado >= 400) {
            return Respuesta::json($estado, ['error' => $resultado->codigo] + $resultado->datos);
        }

        return Respuesta::json($estado, ($resultado->codigo === null ? [] : ['codigo' => $resultado->codigo]) + $resultado->datos, $cabeceras);
    }

    public static function error(int $estado, string $codigo, array $extra = []): Respuesta
    {
        return Respuesta::json($estado, ['error' => $codigo] + $extra);
    }
}

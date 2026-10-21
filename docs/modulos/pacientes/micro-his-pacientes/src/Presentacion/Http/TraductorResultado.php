<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http;

use Pacientes\Aplicacion\Resultados\Resultado;

/** Único lugar donde «qué pasó» se convierte en código HTTP. */
final class TraductorResultado
{
    private const ESTADOS = [
        Resultado::CREADO => 201,
        Resultado::OK => 200,
        'invalido' => 422,
        'conflicto' => 409,
        'no_encontrado' => 404,
        'prohibido' => 403,
        Resultado::NO_DISPONIBLE => 503,
    ];

    /** @param array<string, string> $cabecerasSiExito */
    public static function aRespuesta(Resultado $resultado, array $cabecerasSiExito = []): Respuesta
    {
        $estado = self::ESTADOS[$resultado->estado] ?? 500;
        if ($resultado->exitoso()) {
            return Respuesta::json($estado, $resultado->datos, $cabecerasSiExito);
        }

        return Respuesta::json($estado, ['error' => $resultado->error] + $resultado->datos);
    }
}

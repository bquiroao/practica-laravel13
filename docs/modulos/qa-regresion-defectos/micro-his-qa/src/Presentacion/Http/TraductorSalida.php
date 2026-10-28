<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http;

use Qa\Aplicacion\Resultados\Salida;

/** Traduce la salida de un caso de uso a HTTP. Es el único lugar que conoce los códigos de estado. */
final class TraductorSalida
{
    private const ESTADOS = [
        Salida::CREADO => 201,
        Salida::OK => 200,
        Salida::INVALIDO => 422,
        Salida::CONFLICTO => 409,
        Salida::NO_ENCONTRADO => 404,
        Salida::DENEGADO => 403,
        Salida::DEMASIADO_GRANDE => 413,
        Salida::NO_DISPONIBLE => 503,
    ];

    public function traducir(Salida $s, ?string $location = null): Respuesta
    {
        $estado = self::ESTADOS[$s->tipo];
        $clave = $estado >= 400 ? 'error' : 'codigo';

        return Respuesta::json($estado, [$clave => $s->codigo] + $s->datos, $location !== null && $estado === 201 ? ['Location' => $location] : []);
    }
}

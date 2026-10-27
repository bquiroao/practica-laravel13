<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http;

use Prescripciones\Aplicacion\Resultados\Resultado;

/** Traduce el resultado de un caso de uso a HTTP. Es el único lugar que conoce los códigos de estado. */
final class TraductorResultado
{
    private const ESTADOS = [
        Resultado::CREADO => 201,
        Resultado::OK => 200,
        Resultado::RECHAZADO => 422,
        Resultado::CONFLICTO => 409,
        Resultado::NO_ENCONTRADO => 404,
        Resultado::DENEGADO => 403,
        Resultado::NO_DISPONIBLE => 503,
    ];

    public function traducir(Resultado $r, ?string $location = null): Respuesta
    {
        $estado = self::ESTADOS[$r->tipo];
        $clave = $estado >= 400 ? 'error' : 'codigo';

        return Respuesta::json($estado, [$clave => $r->codigo] + $r->datos, $location !== null && $estado === 201 ? ['Location' => $location] : []);
    }
}

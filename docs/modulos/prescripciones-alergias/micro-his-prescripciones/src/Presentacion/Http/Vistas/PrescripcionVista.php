<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http\Vistas;

use Prescripciones\Aplicacion\Resultados\Resultado;
use Prescripciones\Presentacion\Http\Respuesta;

/** Vista del MVC: Resultado → Respuesta. Junto con ErrorVista, es el único lugar con códigos HTTP. */
final class PrescripcionVista
{
    private const BASE = '/api/v1/prescripciones';
    private const ESTADOS = [
        Resultado::CREADO => 201,
        Resultado::OK => 200,
        Resultado::RECHAZADO => 422,
        Resultado::CONFLICTO => 409,
        Resultado::NO_ENCONTRADO => 404,
        Resultado::DENEGADO => 403,
        Resultado::NO_DISPONIBLE => 503,
    ];

    public static function verificacion(Resultado $r): Respuesta
    {
        return self::json($r);
    }

    /** 201 lleva Location; un reintento (200) no, porque no creó nada. */
    public static function emision(Resultado $r): Respuesta
    {
        return self::json($r, $r->tipo === Resultado::CREADO ? ['Location' => self::BASE . '/' . $r->datos['id']] : []);
    }

    public static function detalle(Resultado $r): Respuesta
    {
        return self::json($r);
    }

    /** @param array<string, string> $cabeceras */
    private static function json(Resultado $r, array $cabeceras = []): Respuesta
    {
        $estado = self::ESTADOS[$r->tipo];

        return Respuesta::json($estado, [($estado >= 400 ? 'error' : 'codigo') => $r->codigo] + $r->datos, $cabeceras);
    }
}

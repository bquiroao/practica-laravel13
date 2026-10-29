<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Vistas;

use Qa\Aplicacion\Resultados\Salida;
use Qa\Presentacion\Http\Respuesta;

/**
 * La V de MVC: convierte la salida de un caso de uso en la respuesta HTTP. Único lugar que conoce los códigos de
 * estado (en la semana 3 era TraductorSalida). Los éxitos llevan «codigo»; los errores, «error».
 */
final class SalidaVista
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

    public function mostrar(Salida $s, ?string $location = null): Respuesta
    {
        $estado = self::ESTADOS[$s->tipo];
        $clave = $estado >= 400 ? 'error' : 'codigo';

        return Respuesta::json($estado, [$clave => $s->codigo] + $s->datos, $location !== null && $estado === 201 ? ['Location' => $location] : []);
    }
}

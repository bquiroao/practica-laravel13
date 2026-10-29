<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http\Vistas;

use E2e\Aplicacion\Resultados\Salida;
use E2e\Presentacion\Http\Respuesta;

/** La V de MVC: la salida de un caso de uso → respuesta HTTP. Único lugar que conoce los códigos de estado. */
final class SalidaVista
{
    private const ESTADOS = [Salida::CREADO => 201, Salida::OK => 200, Salida::INVALIDO => 422, Salida::CONFLICTO => 409, Salida::NO_ENCONTRADO => 404,
        Salida::DENEGADO => 403, Salida::DEMASIADO_GRANDE => 413, Salida::NO_DISPONIBLE => 503];

    public function mostrar(Salida $s, ?string $location = null): Respuesta
    {
        $estado = self::ESTADOS[$s->tipo];

        return new Respuesta($estado, [$estado >= 400 ? 'error' : 'codigo' => $s->codigo] + $s->datos,
            $location !== null && $estado === 201 ? ['Location' => $location] : []);
    }
}

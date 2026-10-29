<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Vistas;

use Qa\Presentacion\Http\Formularios\EntradaInvalida;
use Qa\Presentacion\Http\Respuesta;

/** Respuesta 400 para una entrada que no tiene la forma esperada; dice qué campos, nunca el valor recibido. */
final class ErrorVista
{
    public function entrada(EntradaInvalida $e): Respuesta
    {
        return Respuesta::json(400, ['error' => $e->codigo] + ($e->campos === [] ? [] : ['detalle' => $e->campos]));
    }
}

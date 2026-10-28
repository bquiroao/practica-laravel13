<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http\Vistas;

use Prescripciones\Presentacion\Http\Formularios\EntradaInvalida;
use Prescripciones\Presentacion\Http\Respuesta;

final class ErrorVista
{
    public static function entradaInvalida(EntradaInvalida $e): Respuesta
    {
        return Respuesta::json(400, ['error' => $e->codigo]);
    }
}

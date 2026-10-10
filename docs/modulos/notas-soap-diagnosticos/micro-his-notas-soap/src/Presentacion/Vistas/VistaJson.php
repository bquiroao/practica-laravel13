<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Vistas;

use NotasSoap\Aplicacion\Resultados\NotaResultado;
use NotasSoap\Presentacion\Http\Respuesta;

/** Representación JSON: la usan el frontend del HIS, curl y Postman. */
final class VistaJson implements Vista
{
    public function nota(NotaResultado $nota, int $estado): Respuesta
    {
        return Respuesta::json($estado, $nota->comoArreglo());
    }

    public function error(int $estado, string $codigo, string $mensaje): Respuesta
    {
        return Respuesta::error($estado, $codigo, $mensaje);
    }
}

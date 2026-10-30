<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http\Vistas;

use E2e\Presentacion\Http\Formularios\EntradaInvalida;
use E2e\Presentacion\Http\Respuesta;

/** Errores que no llegan al caso de uso: forma (400), identidad (401/403), ruta (404/405) e inesperados (500, sin detalles). */
final class ErrorVista
{
    public function entrada(EntradaInvalida $e): Respuesta
    {
        return new Respuesta(400, ['error' => $e->codigo] + ($e->campos === [] ? [] : ['campos' => $e->campos]));
    }

    public function estado(int $estado, string $codigo, array $cabeceras = []): Respuesta
    {
        return new Respuesta($estado, ['error' => $codigo], $cabeceras);
    }
}

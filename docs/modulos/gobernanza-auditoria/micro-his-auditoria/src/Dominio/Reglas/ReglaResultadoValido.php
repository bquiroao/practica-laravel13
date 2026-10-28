<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Reglas;

use Auditoria\Dominio\Modelo\Rechazo;
use Auditoria\Dominio\Modelo\SolicitudRegistro;

/** R-01: el resultado del movimiento es exito, denegado o error. */
final class ReglaResultadoValido implements ReglaMovimiento
{
    public const RESULTADOS = ['exito', 'denegado', 'error'];

    public function evaluar(SolicitudRegistro $s): ?Rechazo
    {
        return in_array($s->datos['resultado'], self::RESULTADOS, true)
            ? null : Rechazo::invalido(['resultado' => 'Debe ser exito, denegado o error.']);
    }
}

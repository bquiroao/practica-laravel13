<?php

declare(strict_types=1);

namespace Auditoria\Reglas;

use Auditoria\Contratos\ReglaMovimiento;
use Auditoria\Dominio\Rechazo;
use Auditoria\Dominio\SolicitudRegistro;

/** R-01: informa TODOS los campos obligatorios que faltan, no solo el primero. */
final class ReglaCamposObligatorios implements ReglaMovimiento
{
    private const CAMPOS = ['event_id', 'usuario', 'modulo', 'accion', 'ocurrido_en', 'resultado'];

    public function evaluar(SolicitudRegistro $s): ?Rechazo
    {
        $faltan = [];
        foreach (self::CAMPOS as $campo) {
            $valor = $s->datos[$campo] ?? null;
            if (!is_string($valor) || trim($valor) === '') {
                $faltan[$campo] = 'Obligatorio.';
            }
        }

        return $faltan === [] ? null : Rechazo::invalido($faltan);
    }
}

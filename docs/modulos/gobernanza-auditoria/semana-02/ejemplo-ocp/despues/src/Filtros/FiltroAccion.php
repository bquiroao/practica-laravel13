<?php

declare(strict_types=1);

namespace Auditoria\Filtros;

use Auditoria\Dominio\Movimiento;

final class FiltroAccion extends FiltroPorCampo
{
    protected function parametro(): string
    {
        return 'accion';
    }

    protected function columna(): string
    {
        return 'accion';
    }

    protected function valorDe(Movimiento $m): string
    {
        return $m->accion;
    }
}

<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Filtros;

use Auditoria\Dominio\Modelo\Movimiento;

final class FiltroModulo extends FiltroPorCampo
{
    protected function parametro(): string
    {
        return 'modulo';
    }

    protected function columna(): string
    {
        return 'modulo';
    }

    protected function valorDe(Movimiento $m): string
    {
        return $m->modulo;
    }
}

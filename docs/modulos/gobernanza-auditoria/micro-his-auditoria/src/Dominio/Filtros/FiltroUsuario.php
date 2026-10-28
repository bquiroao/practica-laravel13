<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Filtros;

use Auditoria\Dominio\Modelo\Movimiento;

final class FiltroUsuario extends FiltroPorCampo
{
    protected function parametro(): string
    {
        return 'usuario';
    }

    protected function columna(): string
    {
        return 'usuario_id';
    }

    protected function valorDe(Movimiento $m): string
    {
        return $m->usuario;
    }
}

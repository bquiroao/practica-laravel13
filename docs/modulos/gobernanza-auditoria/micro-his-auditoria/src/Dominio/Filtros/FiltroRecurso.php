<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Filtros;

use Auditoria\Dominio\Modelo\Movimiento;

/**
 * Semana 12 (cambio solicitado): «¿Quién vio la ficha de este paciente?». Una extensión OCP más:
 * ni el caso de uso ni el catálogo cambian. Un movimiento sin recurso nunca coincide.
 */
final class FiltroRecurso extends FiltroPorCampo
{
    protected function parametro(): string
    {
        return 'recurso';
    }

    protected function columna(): string
    {
        return 'recurso';
    }

    protected function valorDe(Movimiento $m): string
    {
        return $m->recurso ?? '';
    }

    protected function maximo(): int
    {
        return 128;
    }
}

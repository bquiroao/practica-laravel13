<?php

declare(strict_types=1);

namespace Auditoria\Extension;

use Auditoria\Contratos\FiltroMovimiento;
use Auditoria\Dominio\Movimiento;

/**
 * EXTENSIÓN (requerimiento nuevo, sin modificar el núcleo):
 * «Gobernanza necesita ver solo los movimientos denegados o con error de un usuario».
 */
final class FiltroResultado implements FiltroMovimiento
{
    private const VALORES = ['exito', 'denegado', 'error'];

    public function parametros(): array
    {
        return ['resultado'];
    }

    public function obligatorio(): bool
    {
        return false;
    }

    public function activo(array $p): bool
    {
        return isset($p['resultado']);
    }

    public function validar(array $p): array
    {
        return in_array($p['resultado'], self::VALORES, true) ? [] : ['resultado' => 'Debe ser exito, denegado o error.'];
    }

    public function coincide(Movimiento $m, array $p): bool
    {
        return $m->resultado === $p['resultado'];
    }

    public function sql(array $p): array
    {
        return ['resultado = :resultado', ['resultado' => $p['resultado']]];
    }
}

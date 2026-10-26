<?php

declare(strict_types=1);

namespace Auditoria\Dominio;

use Auditoria\Contratos\FiltroMovimiento;

/**
 * Consulta ya validada que recibe la Bitacora. El alcance (hospital o red) NO es un filtro
 * intercambiable: lo fija el servicio, y ninguna composición puede quitarlo (R-05).
 */
final class ConsultaMovimientos
{
    /**
     * @param list<FiltroMovimiento> $filtros los filtros activos
     * @param array<string, string> $parametros
     */
    public function __construct(
        public readonly ?string $hospital,
        public readonly bool $red,
        public readonly array $filtros,
        public readonly array $parametros,
        public readonly int $limite,
    ) {
    }

    public function coincide(Movimiento $m): bool
    {
        if (!$this->red && $m->hospital !== $this->hospital) {
            return false;
        }
        foreach ($this->filtros as $filtro) {
            if (!$filtro->coincide($m, $this->parametros)) {
                return false;
            }
        }

        return true;
    }
}

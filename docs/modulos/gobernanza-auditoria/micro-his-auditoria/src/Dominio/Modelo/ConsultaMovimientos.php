<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Modelo;

use Auditoria\Dominio\Filtros\FiltroMovimiento;

/**
 * Consulta ya validada que recibe la Bitacora. El alcance (hospital o red) NO es un filtro
 * intercambiable: lo fija el caso de uso, y ninguna composición puede quitarlo (R-05).
 * El cursor (R-09) continúa después de (ocurrido_en, id) de la última fila de la página anterior.
 */
final class ConsultaMovimientos
{
    /**
     * @param list<FiltroMovimiento> $filtros los filtros activos
     * @param array<string, string> $parametros
     * @param array{0: \DateTimeImmutable, 1: int}|null $despuesDe
     */
    public function __construct(
        public readonly ?string $hospital,
        public readonly bool $red,
        public readonly array $filtros,
        public readonly array $parametros,
        public readonly int $limite,
        public readonly ?array $despuesDe = null,
    ) {
    }

    public function coincide(Movimiento $m): bool
    {
        if (!$this->red && $m->hospital !== $this->hospital) {
            return false;
        }
        if ($this->despuesDe !== null && [$m->ocurridoEn, $m->id] >= $this->despuesDe) {
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

<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;

/**
 * Mortalidad hospitalaria: defunciones ÷ egresos × 100. Es un indicador sensible: las salas o grupos de edad con
 * menos de 3 defunciones se agrupan como «Otros» sin cambiar el total (RN-04, CA-06).
 */
class MortalityIndicator extends Indicator
{
    use Discharges;

    public function key(): string
    {
        return 'mortalidad';
    }

    public function name(): string
    {
        return 'Mortalidad hospitalaria';
    }

    public function area(): string
    {
        return 'Hospitalización';
    }

    public function unit(): string
    {
        return '%';
    }

    public function description(): string
    {
        return 'De cada 100 pacientes que egresaron, cuántos fallecieron.';
    }

    public function formula(): string
    {
        return 'egresos por defunción / total de egresos × 100';
    }

    public function source(): string
    {
        return 'care_episodes (internamientos cerrados, tipo de alta), bed_stays, wards, local_patients';
    }

    public function dimensions(): array
    {
        return ['sala' => 'Última sala', 'grupo_edad' => 'Grupo de edad'];
    }

    public function columns(): array
    {
        return $this->dischargeColumns();
    }

    public function numeratorLabel(): string
    {
        return 'defunciones';
    }

    public function denominatorLabel(): string
    {
        return 'egresos';
    }

    public function feeds(): array
    {
        return ['traslados-altas-camas'];
    }

    public function items(Filters $f): array
    {
        return array_values(array_map(fn ($d) => ['peso' => 1, 'dims' => ['sala' => $d['sala'], 'grupo_edad' => self::ageGroup($d['edad'])], 'fila' => $d['fila']],
            array_filter($this->discharges($f), fn ($d) => $d['defuncion'])));
    }

    public function denominator(Filters $f, array $items): int
    {
        return $this->dischargeCount($f);
    }
}

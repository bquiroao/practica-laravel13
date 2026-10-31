<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;

/**
 * Estancia promedio (ALOS), el cambio práctico de la semana 6: días de estancia de los egresos ÷ número de egresos.
 * Un promedio no se desglosa sumando promedios: el desglose reparte los días de estancia y el número de egresos va
 * en la ficha, así la suma de las partes sigue siendo el numerador (RN-07).
 */
class LengthOfStayIndicator extends Indicator
{
    use Discharges;

    public function key(): string
    {
        return 'estancia_promedio';
    }

    public function name(): string
    {
        return 'Estancia promedio';
    }

    public function area(): string
    {
        return 'Hospitalización';
    }

    public function unit(): string
    {
        return 'dias';
    }

    public function description(): string
    {
        return 'Cuántos días pasa internado, en promedio, un paciente que egresó en el período.';
    }

    public function formula(): string
    {
        return 'días de estancia de los egresos / número de egresos';
    }

    public function source(): string
    {
        return 'care_episodes (internamientos cerrados), bed_stays, wards, local_patients';
    }

    public function dimensions(): array
    {
        return ['sala' => 'Última sala', 'tipo_alta' => 'Tipo de egreso'];
    }

    public function columns(): array
    {
        return $this->dischargeColumns();
    }

    public function numeratorLabel(): string
    {
        return 'días de estancia';
    }

    public function denominatorLabel(): string
    {
        return 'egresos';
    }

    public function note(): string
    {
        return 'El desglose reparte días de estancia; el promedio de cada parte se calcula con sus propios egresos, nunca promediando promedios.';
    }

    public function feeds(): array
    {
        return ['admision-hospitalaria-asignacion-cama', 'traslados-altas-camas'];
    }

    public function items(Filters $f): array
    {
        return array_map(fn ($d) => ['peso' => $d['dias'], 'dims' => ['sala' => $d['sala'], 'tipo_alta' => $d['tipo_alta']], 'fila' => $d['fila']], $this->discharges($f));
    }

    public function denominator(Filters $f, array $items): int
    {
        return count($items);
    }
}

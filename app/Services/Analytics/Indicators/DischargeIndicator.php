<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;

/** Egresos hospitalarios del período: altas médicas, voluntarias, referencias y defunciones. */
class DischargeIndicator extends Indicator
{
    use Discharges;

    public function key(): string
    {
        return 'egresos';
    }

    public function name(): string
    {
        return 'Egresos hospitalarios';
    }

    public function area(): string
    {
        return 'Hospitalización';
    }

    public function unit(): string
    {
        return 'cantidad';
    }

    public function description(): string
    {
        return 'Pacientes internados que salieron del hospital en el período, por cualquier motivo.';
    }

    public function formula(): string
    {
        return 'número de internamientos cerrados en el período';
    }

    public function source(): string
    {
        return 'care_episodes (internamientos cerrados), bed_stays, wards, local_patients';
    }

    public function dimensions(): array
    {
        return ['tipo_alta' => 'Tipo de egreso', 'sala' => 'Última sala'];
    }

    public function columns(): array
    {
        return $this->dischargeColumns();
    }

    public function numeratorLabel(): string
    {
        return 'egresos';
    }

    public function feeds(): array
    {
        return ['traslados-altas-camas'];
    }

    public function items(Filters $f): array
    {
        return array_map(fn ($d) => ['peso' => 1, 'dims' => ['tipo_alta' => $d['tipo_alta'], 'sala' => $d['sala']], 'fila' => $d['fila']], $this->discharges($f));
    }
}

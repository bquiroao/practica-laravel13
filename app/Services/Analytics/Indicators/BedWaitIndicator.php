<?php

namespace App\Services\Analytics\Indicators;

use App\Models\Hospital\AdmissionOrder;
use App\Models\Hospital\Bed;
use App\Services\Admissions\AdmissionOrderService;
use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;

/**
 * Tiempo de espera de cama: minutos entre la orden de ingreso y la asignación de la cama, en promedio, de las órdenes
 * que recibieron cama en el período. Las admisiones directas, sin orden previa, no tienen espera que medir.
 */
class BedWaitIndicator extends Indicator
{
    public function key(): string
    {
        return 'espera_cama';
    }

    public function name(): string
    {
        return 'Tiempo de espera de cama';
    }

    public function area(): string
    {
        return 'Hospitalización';
    }

    public function unit(): string
    {
        return 'minutos';
    }

    public function description(): string
    {
        return 'Cuánto espera, en promedio, un paciente con orden de ingreso hasta que se le asigna una cama.';
    }

    public function formula(): string
    {
        return 'minutos entre la orden de ingreso y la cama asignada / órdenes con cama asignada en el período';
    }

    public function source(): string
    {
        return 'admission_orders, local_patients';
    }

    public function dimensions(): array
    {
        return ['prioridad' => 'Prioridad', 'tipo_cama' => 'Tipo de cama'];
    }

    public function columns(): array
    {
        return ['orden' => 'Orden de ingreso', 'asignada' => 'Cama asignada', 'paciente' => 'Paciente', 'prioridad' => 'Prioridad', 'tipo_cama' => 'Tipo de cama', 'minutos' => 'Minutos de espera'];
    }

    public function numeratorLabel(): string
    {
        return 'minutos de espera';
    }

    public function denominatorLabel(): string
    {
        return 'órdenes con cama asignada';
    }

    public function note(): string
    {
        return 'Solo cuentan las admisiones que empezaron con una orden de ingreso. El desglose reparte minutos; el promedio de cada parte usa sus propias órdenes.';
    }

    public function feeds(): array
    {
        return ['admision-hospitalaria-asignacion-cama'];
    }

    public function items(Filters $f): array
    {
        return $this->query($f)->with('patient')->get()->map(function (AdmissionOrder $o) use ($f) {
            $minutes = (int) $o->ordered_at->diffInMinutes($o->assigned_at);
            $priority = AdmissionOrderService::PRIORITIES[$o->priority];
            $type = Bed::TYPES[$o->bed_type];

            return ['peso' => $minutes, 'dims' => ['prioridad' => $priority, 'tipo_cama' => $type],
                'fila' => ['orden' => $f->localDateTime($o->ordered_at), 'asignada' => $f->localDateTime($o->assigned_at), 'paciente' => $o->patient->code,
                    'prioridad' => $priority, 'tipo_cama' => $type, 'minutos' => $minutes]];
        })->all();
    }

    public function denominator(Filters $f, array $items): int
    {
        return $this->query($f)->count();
    }

    private function query(Filters $f)
    {
        return AdmissionOrder::query()->where('status', 'asignada')
            ->when($f->started(), fn ($q) => $q->whereBetween('assigned_at', $f->range()), fn ($q) => $q->whereRaw('false'))
            ->orderBy('assigned_at')->orderBy('admission_order_id');
    }
}

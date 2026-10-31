<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;
use Illuminate\Support\Facades\DB;

/** Pruebas de laboratorio solicitadas en el período, por prueba, estado y prioridad. */
class LabTestIndicator extends Indicator
{
    private const STATUS = ['pending' => 'Pendiente de muestra', 'received' => 'En proceso', 'rejected' => 'Muestra rechazada', 'resulted' => 'Por validar', 'validated' => 'Validada'];

    public function key(): string
    {
        return 'laboratorio';
    }

    public function name(): string
    {
        return 'Pruebas de laboratorio';
    }

    public function area(): string
    {
        return 'Laboratorio';
    }

    public function unit(): string
    {
        return 'cantidad';
    }

    public function description(): string
    {
        return 'Pruebas pedidas desde el expediente en el período y en qué paso del laboratorio van.';
    }

    public function formula(): string
    {
        return 'número de pruebas pedidas en el período';
    }

    public function source(): string
    {
        return 'lab_order_items, lab_orders, local_patients';
    }

    public function dimensions(): array
    {
        return ['prueba' => 'Prueba', 'estado' => 'Estado', 'prioridad' => 'Prioridad'];
    }

    public function columns(): array
    {
        return ['fecha' => 'Pedida', 'paciente' => 'Paciente', 'prueba' => 'Prueba', 'prioridad' => 'Prioridad', 'estado' => 'Estado'];
    }

    public function numeratorLabel(): string
    {
        return 'pruebas';
    }

    /** Las pruebas son un catálogo, no identifican pacientes: se publican aunque sean pocas. */
    public function groupsSmallCases(string $dimension): bool
    {
        return false;
    }

    public function feeds(): array
    {
        return ['ordenes-laboratorio-emr', 'recepcion-muestras', 'ingreso-resultados-laboratorio', 'validacion-resultados-bioquimico', 'catalogo-pruebas-laboratorio'];
    }

    public function items(Filters $f): array
    {
        if (! $f->started()) {
            return [];
        }

        return DB::connection('hospital')->table('lab_order_items as i')
            ->join('lab_orders as o', 'o.lab_order_id', '=', 'i.lab_order_id')
            ->join('local_patients as p', 'p.local_patient_id', '=', 'o.local_patient_id')
            ->where('o.status', '<>', 'cancelled')
            ->whereBetween('i.created_at', $f->range())
            ->orderBy('i.created_at')->orderBy('i.lab_order_item_id')
            ->get(['i.created_at', 'i.test_name', 'i.status', 'o.priority', 'p.code as patient'])
            ->map(function ($r) use ($f) {
                $status = self::STATUS[$r->status] ?? $r->status;
                $priority = $r->priority === 'urgent' ? 'Urgente' : 'Rutina';

                return ['peso' => 1, 'dims' => ['prueba' => $r->test_name, 'estado' => $status, 'prioridad' => $priority],
                    'fila' => ['fecha' => $f->localDateTime($r->created_at), 'paciente' => $r->patient, 'prueba' => $r->test_name, 'prioridad' => $priority, 'estado' => $status]];
            })->all();
    }
}

<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;
use Illuminate\Support\Facades\DB;

/** Resultados críticos: resultados validados con marca crítica ÷ resultados validados del período × 100. */
class CriticalLabResultIndicator extends Indicator
{
    public function key(): string
    {
        return 'laboratorio_criticos';
    }

    public function name(): string
    {
        return 'Resultados críticos de laboratorio';
    }

    public function area(): string
    {
        return 'Laboratorio';
    }

    public function unit(): string
    {
        return '%';
    }

    public function description(): string
    {
        return 'De cada 100 resultados validados, cuántos tuvieron un valor crítico que exige avisar al médico.';
    }

    public function formula(): string
    {
        return 'resultados validados con valor crítico / resultados validados × 100';
    }

    public function source(): string
    {
        return 'lab_results, lab_order_items, lab_orders, local_patients';
    }

    public function dimensions(): array
    {
        return ['prueba' => 'Prueba', 'nivel' => 'Marca'];
    }

    public function columns(): array
    {
        return ['fecha' => 'Validado', 'paciente' => 'Paciente', 'prueba' => 'Prueba', 'nivel' => 'Marca'];
    }

    public function numeratorLabel(): string
    {
        return 'resultados críticos';
    }

    public function denominatorLabel(): string
    {
        return 'resultados validados';
    }

    public function groupsSmallCases(string $dimension): bool
    {
        return false;
    }

    public function feeds(): array
    {
        return ['validacion-resultados-bioquimico', 'alertas-criticas-notificaciones-internas'];
    }

    public function items(Filters $f): array
    {
        return $this->query($f)->whereIn('r.flag', ['critical_low', 'critical_high'])
            ->join('local_patients as p', 'p.local_patient_id', '=', 'o.local_patient_id')
            ->orderBy('r.validated_at')->orderBy('r.lab_result_id')
            ->get(['r.validated_at', 'r.flag', 'i.test_name', 'p.code as patient'])
            ->map(function ($r) use ($f) {
                $flag = $r->flag === 'critical_low' ? 'Crítico bajo' : 'Crítico alto';

                return ['peso' => 1, 'dims' => ['prueba' => $r->test_name, 'nivel' => $flag],
                    'fila' => ['fecha' => $f->localDateTime($r->validated_at), 'paciente' => $r->patient, 'prueba' => $r->test_name, 'nivel' => $flag]];
            })->all();
    }

    public function denominator(Filters $f, array $items): int
    {
        return $this->query($f)->count();
    }

    private function query(Filters $f)
    {
        return DB::connection('hospital')->table('lab_results as r')
            ->join('lab_order_items as i', 'i.lab_order_item_id', '=', 'r.lab_order_item_id')
            ->join('lab_orders as o', 'o.lab_order_id', '=', 'i.lab_order_id')
            ->where('r.status', 'validated')
            ->when($f->started(), fn ($q) => $q->whereBetween('r.validated_at', $f->range()), fn ($q) => $q->whereRaw('false'));
    }
}

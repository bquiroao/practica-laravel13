<?php

namespace App\Services\Analytics\Indicators;

use App\Models\Hospital\LabTest;
use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;
use App\Services\LabCatalog\LabTestOptions;
use Illuminate\Support\Facades\DB;

/**
 * Pruebas por área del laboratorio: las pruebas pedidas en el período, agrupadas por la sección que define el catálogo
 * de pruebas (hematología, química clínica…). La sección sale de la ficha del catálogo, no de una copia en la orden.
 */
class LabSectionIndicator extends Indicator
{
    public function key(): string
    {
        return 'pruebas_seccion';
    }

    public function name(): string
    {
        return 'Pruebas por área del laboratorio';
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
        return 'Pruebas pedidas en el período según la sección del laboratorio que las procesa, para repartir el trabajo.';
    }

    public function formula(): string
    {
        return 'número de pruebas pedidas en el período (sin órdenes canceladas), por sección del catálogo';
    }

    public function source(): string
    {
        return 'lab_order_items, lab_orders, lab_tests, local_patients';
    }

    public function dimensions(): array
    {
        return ['seccion' => 'Sección', 'prueba' => 'Prueba'];
    }

    public function columns(): array
    {
        return ['fecha' => 'Pedida', 'paciente' => 'Paciente', 'prueba' => 'Prueba', 'seccion' => 'Sección'];
    }

    public function numeratorLabel(): string
    {
        return 'pruebas';
    }

    /** Las secciones y las pruebas no identifican pacientes: se publican aunque sean pocas. */
    public function groupsSmallCases(string $dimension): bool
    {
        return false;
    }

    public function note(): ?string
    {
        return 'Una prueba que ya no está en el catálogo se cuenta como «Fuera del catálogo».';
    }

    public function feeds(): array
    {
        return ['catalogo-pruebas-laboratorio', 'ordenes-laboratorio-emr'];
    }

    public function items(Filters $f): array
    {
        if (! $f->started()) {
            return [];
        }
        // La sección de la última versión de cada código.
        $sections = LabTest::query()->orderBy('version')->pluck('section', 'code')->map(fn (string $s) => LabTestOptions::SECTIONS[$s]);

        return DB::connection('hospital')->table('lab_order_items as i')
            ->join('lab_orders as o', 'o.lab_order_id', '=', 'i.lab_order_id')
            ->join('local_patients as p', 'p.local_patient_id', '=', 'o.local_patient_id')
            ->where('o.status', '<>', 'cancelled')
            ->whereBetween('i.created_at', $f->range())
            ->orderBy('i.created_at')->orderBy('i.lab_order_item_id')
            ->get(['i.created_at', 'i.test_code', 'i.test_name', 'p.code as patient'])
            ->map(function ($r) use ($f, $sections) {
                $section = $sections[$r->test_code] ?? 'Fuera del catálogo';

                return ['peso' => 1, 'dims' => ['seccion' => $section, 'prueba' => $r->test_name],
                    'fila' => ['fecha' => $f->localDateTime($r->created_at), 'paciente' => $r->patient, 'prueba' => $r->test_name, 'seccion' => $section]];
            })->all();
    }
}

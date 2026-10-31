<?php

namespace App\Services\Analytics\Indicators;

use App\Models\Hospital\Medication;
use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;
use App\Services\Medications\MedicationOptions;
use Illuminate\Support\Facades\DB;

/**
 * Recetas por grupo terapéutico: las recetas del período agrupadas según el catálogo de medicamentos. El grupo sale de
 * la ficha del medicamento (por su código o por el código del catálogo provisional), no de una copia en la receta.
 */
class PrescriptionGroupIndicator extends Indicator
{
    public function key(): string
    {
        return 'recetas_grupo';
    }

    public function name(): string
    {
        return 'Recetas por grupo terapéutico';
    }

    public function area(): string
    {
        return 'Farmacia';
    }

    public function unit(): string
    {
        return 'cantidad';
    }

    public function description(): string
    {
        return 'Recetas del período según el grupo terapéutico del catálogo oficial de medicamentos (antibióticos, analgésicos…).';
    }

    public function formula(): string
    {
        return 'número de recetas emitidas en el período, por grupo terapéutico del medicamento';
    }

    public function source(): string
    {
        return 'prescriptions, medications, local_patients';
    }

    public function dimensions(): array
    {
        return ['grupo' => 'Grupo terapéutico', 'medicamento' => 'Medicamento'];
    }

    public function columns(): array
    {
        return ['fecha' => 'Emitida', 'paciente' => 'Paciente', 'medicamento' => 'Medicamento', 'grupo' => 'Grupo terapéutico', 'estado' => 'Estado'];
    }

    public function numeratorLabel(): string
    {
        return 'recetas';
    }

    /** Los grupos y los medicamentos no identifican pacientes: se publican aunque sean pocos. */
    public function groupsSmallCases(string $dimension): bool
    {
        return false;
    }

    public function note(): ?string
    {
        return 'Una receta de un medicamento que ya no está en el catálogo se cuenta como «Fuera del catálogo».';
    }

    public function feeds(): array
    {
        return ['catalogo-medicamentos', 'prescripciones-alergias'];
    }

    public function items(Filters $f): array
    {
        if (! $f->started()) {
            return [];
        }
        // El grupo de la última versión de cada código; también por el código provisional (AMOX500…).
        $groups = [];
        foreach (Medication::query()->orderBy('version')->get(['code', 'legacy_code', 'therapeutic_group']) as $m) {
            $groups[$m->code] = $groups[$m->legacy_code ?? $m->code] = MedicationOptions::GROUPS[$m->therapeutic_group];
        }

        return DB::connection('hospital')->table('prescriptions as r')
            ->join('local_patients as p', 'p.local_patient_id', '=', 'r.local_patient_id')
            ->whereBetween('r.issued_at', $f->range())
            ->orderBy('r.issued_at')->orderBy('r.prescription_id')
            ->get(['r.issued_at', 'r.medication_code', 'r.medication_name', 'r.status', 'p.code as patient'])
            ->map(function ($r) use ($f, $groups) {
                $group = $groups[$r->medication_code] ?? 'Fuera del catálogo';
                $status = $r->status === 'issued' ? 'Vigente' : 'Anulada';

                return ['peso' => 1, 'dims' => ['grupo' => $group, 'medicamento' => $r->medication_name],
                    'fila' => ['fecha' => $f->localDateTime($r->issued_at), 'paciente' => $r->patient, 'medicamento' => $r->medication_name, 'grupo' => $group, 'estado' => $status]];
            })->all();
    }
}

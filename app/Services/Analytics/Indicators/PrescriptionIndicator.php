<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;
use Illuminate\Support\Facades\DB;

/** Recetas emitidas en el período: los medicamentos más recetados y quién los receta. */
class PrescriptionIndicator extends Indicator
{
    public function key(): string
    {
        return 'medicamentos';
    }

    public function name(): string
    {
        return 'Recetas emitidas';
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
        return 'Medicamentos recetados en el período, incluidas las recetas que después se anularon.';
    }

    public function formula(): string
    {
        return 'número de recetas emitidas en el período';
    }

    public function source(): string
    {
        return 'prescriptions, local_user_refs, local_patients';
    }

    public function dimensions(): array
    {
        return ['medicamento' => 'Medicamento', 'estado' => 'Estado', 'medico' => 'Médico que receta'];
    }

    public function columns(): array
    {
        return ['fecha' => 'Emitida', 'paciente' => 'Paciente', 'medicamento' => 'Medicamento', 'medico' => 'Médico', 'estado' => 'Estado'];
    }

    public function numeratorLabel(): string
    {
        return 'recetas';
    }

    /** Los medicamentos y los médicos no identifican pacientes: se publican aunque sean pocos. */
    public function groupsSmallCases(string $dimension): bool
    {
        return false;
    }

    public function feeds(): array
    {
        return ['prescripciones-alergias', 'catalogo-medicamentos'];
    }

    public function items(Filters $f): array
    {
        if (! $f->started()) {
            return [];
        }

        return DB::connection('hospital')->table('prescriptions as r')
            ->join('local_patients as p', 'p.local_patient_id', '=', 'r.local_patient_id')
            ->leftJoin('local_user_refs as u', 'u.local_user_ref_id', '=', 'r.prescriber_user_ref_id')
            ->whereBetween('r.issued_at', $f->range())
            ->orderBy('r.issued_at')->orderBy('r.prescription_id')
            ->get(['r.issued_at', 'r.medication_name', 'r.status', 'u.display_name as doctor', 'p.code as patient'])
            ->map(function ($r) use ($f) {
                $status = $r->status === 'issued' ? 'Vigente' : 'Anulada';
                $doctor = $r->doctor ?: 'Sin nombre';

                return ['peso' => 1, 'dims' => ['medicamento' => $r->medication_name, 'estado' => $status, 'medico' => $doctor],
                    'fila' => ['fecha' => $f->localDateTime($r->issued_at), 'paciente' => $r->patient, 'medicamento' => $r->medication_name, 'medico' => $doctor, 'estado' => $status]];
            })->all();
    }
}

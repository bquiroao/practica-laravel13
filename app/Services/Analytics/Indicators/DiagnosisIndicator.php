<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;
use Illuminate\Support\Facades\DB;

/** Diagnósticos registrados (CIE-10): las causas de atención más frecuentes del período. */
class DiagnosisIndicator extends Indicator
{
    private const TYPES = ['primary' => 'Principal', 'secondary' => 'Secundario', 'differential' => 'Diferencial'];

    public function key(): string
    {
        return 'diagnosticos';
    }

    public function name(): string
    {
        return 'Diagnósticos registrados';
    }

    public function area(): string
    {
        return 'Clínico';
    }

    public function unit(): string
    {
        return 'cantidad';
    }

    public function description(): string
    {
        return 'Diagnósticos CIE-10 anotados en las notas del período: muestra las causas de atención más frecuentes.';
    }

    public function formula(): string
    {
        return 'número de diagnósticos registrados en el período (sin los anulados por error)';
    }

    public function source(): string
    {
        return 'diagnoses, care_episodes, local_patients';
    }

    public function dimensions(): array
    {
        return ['diagnostico' => 'Diagnóstico (CIE-10)', 'tipo' => 'Tipo de diagnóstico', 'atencion' => 'Tipo de atención'];
    }

    public function columns(): array
    {
        return ['fecha' => 'Registrado', 'paciente' => 'Paciente', 'codigo' => 'CIE-10', 'tipo' => 'Tipo', 'atencion' => 'Atención'];
    }

    public function numeratorLabel(): string
    {
        return 'diagnósticos';
    }

    public function groupsSmallCases(string $dimension): bool
    {
        return $dimension === 'diagnostico';
    }

    public function feeds(): array
    {
        return ['notas-soap-diagnosticos', 'expediente-medico'];
    }

    public function items(Filters $f): array
    {
        if (! $f->started()) {
            return [];
        }
        $care = ['outpatient' => 'Consulta externa', 'emergency' => 'Emergencia', 'inpatient' => 'Internamiento'];

        return DB::connection('hospital')->table('diagnoses as d')
            ->join('care_episodes as e', 'e.care_episode_id', '=', 'd.care_episode_id')
            ->join('local_patients as p', 'p.local_patient_id', '=', 'e.local_patient_id')
            ->where('d.status', '<>', 'entered_in_error')
            ->whereBetween('d.created_at', $f->range())
            ->orderBy('d.created_at')->orderBy('d.diagnosis_id')
            ->get(['d.code', 'd.description', 'd.type', 'd.created_at', 'e.type as care', 'p.code as patient'])
            ->map(function ($r) use ($f, $care) {
                $code = strtoupper(trim($r->code));
                $type = self::TYPES[$r->type] ?? $r->type;

                return ['peso' => 1, 'dims' => ['diagnostico' => $code.' · '.trim($r->description), 'tipo' => $type, 'atencion' => $care[$r->care] ?? $r->care],
                    'fila' => ['fecha' => $f->localDateTime($r->created_at), 'paciente' => $r->patient, 'codigo' => $code, 'tipo' => $type, 'atencion' => $care[$r->care] ?? $r->care]];
            })->all();
    }
}

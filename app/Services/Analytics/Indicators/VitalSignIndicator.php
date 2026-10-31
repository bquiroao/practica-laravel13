<?php

namespace App\Services\Analytics\Indicators;

use App\Models\Hospital\VitalSign;
use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;
use App\Services\Records\VitalSignService;
use Illuminate\Support\Facades\DB;

/**
 * Tomas de signos vitales fuera de rango: tomas con algún valor anormal o crítico ÷ tomas del período × 100.
 * La clasificación es la misma del módulo de signos vitales (VitalSignService::findings), no una copia.
 */
class VitalSignIndicator extends Indicator
{
    public function key(): string
    {
        return 'signos_vitales';
    }

    public function name(): string
    {
        return 'Signos vitales fuera de rango';
    }

    public function area(): string
    {
        return 'Clínico';
    }

    public function unit(): string
    {
        return '%';
    }

    public function description(): string
    {
        return 'De cada 100 tomas de signos vitales, cuántas tuvieron algún valor anormal o crítico.';
    }

    public function formula(): string
    {
        return 'tomas con algún valor anormal o crítico / tomas del período × 100';
    }

    public function source(): string
    {
        return 'vital_signs, medical_records, local_patients, local_user_refs';
    }

    public function dimensions(): array
    {
        return ['nivel' => 'Nivel más alto', 'rol' => 'Registrada por'];
    }

    public function columns(): array
    {
        return ['fecha' => 'Medición', 'paciente' => 'Paciente', 'nivel' => 'Nivel', 'signos' => 'Signos fuera de rango', 'rol' => 'Registrada por'];
    }

    public function numeratorLabel(): string
    {
        return 'tomas fuera de rango';
    }

    public function denominatorLabel(): string
    {
        return 'tomas del período';
    }

    public function note(): string
    {
        return 'Rangos de adulto del módulo de signos vitales. Una toma corregida cuenta una sola vez, con su versión vigente.';
    }

    public function feeds(): array
    {
        return ['signos-vitales-alertas'];
    }

    public function items(Filters $f): array
    {
        $roles = ['medico' => 'Médico', 'enfermeria' => 'Enfermería'];

        return $this->query($f)
            ->join('medical_records as m', 'm.medical_record_id', '=', 'v.medical_record_id')
            ->join('local_patients as p', 'p.local_patient_id', '=', 'm.local_patient_id')
            ->leftJoin('local_user_refs as u', 'u.local_user_ref_id', '=', 'v.recorded_by_user_ref_id')
            ->orderBy('v.measured_at')->orderBy('v.vital_sign_id')
            ->get(['v.*', 'p.code as patient', 'u.role_code'])
            ->map(function ($r) use ($f, $roles) {
                $findings = VitalSignService::findings((new VitalSign)->forceFill(array_intersect_key((array) $r, array_flip(array_keys(VitalSignService::SIGNS)))));
                if ($findings === []) {
                    return null;
                }
                $level = in_array('critica', array_column($findings, 'nivel'), true) ? 'Crítica' : 'Anormal';
                $role = $roles[$r->role_code] ?? 'Otro personal';

                return ['peso' => 1, 'dims' => ['nivel' => $level, 'rol' => $role],
                    'fila' => ['fecha' => $f->localDateTime($r->measured_at), 'paciente' => $r->patient, 'nivel' => $level,
                        'signos' => implode(', ', array_column($findings, 'etiqueta')), 'rol' => $role]];
            })->filter()->values()->all();
    }

    public function denominator(Filters $f, array $items): int
    {
        return $this->query($f)->count();
    }

    /** Tomas vigentes del período: sin las anuladas por error ni las versiones que ya se corrigieron. */
    private function query(Filters $f)
    {
        return DB::connection('hospital')->table('vital_signs as v')
            ->where('v.status', '<>', 'entered_in_error')
            ->when($f->started(), fn ($q) => $q->whereBetween('v.measured_at', $f->range()), fn ($q) => $q->whereRaw('false'))
            ->whereNotExists(fn ($q) => $q->from('vital_signs as n')->whereColumn('n.supersedes_id', 'v.vital_sign_id')->where('n.status', '<>', 'entered_in_error'));
    }
}

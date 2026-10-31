<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;
use Illuminate\Support\Facades\DB;

/** Alergias registradas en el período, por severidad, tipo y alérgeno. Lo alimenta el módulo de alergias clínicas. */
class AllergyIndicator extends Indicator
{
    private const SEVERITY = ['mild' => 'Leve', 'moderate' => 'Moderada', 'severe' => 'Grave', 'life_threatening' => 'Pone en riesgo la vida', 'unknown' => 'Sin clasificar'];

    private const TYPES = ['allergy' => 'Alergia', 'intolerance' => 'Intolerancia', 'unknown' => 'Sin clasificar'];

    private const STATUS = ['active' => 'Activa', 'inactive' => 'Inactiva', 'resolved' => 'Resuelta'];

    public function key(): string
    {
        return 'alergias';
    }

    public function name(): string
    {
        return 'Alergias registradas';
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
        return 'Alergias e intolerancias anotadas en el período, para conocer los riesgos más comunes.';
    }

    public function formula(): string
    {
        return 'número de alergias registradas en el período';
    }

    public function source(): string
    {
        return 'allergies, local_patients';
    }

    public function dimensions(): array
    {
        return ['severidad' => 'Severidad', 'alergeno' => 'Alérgeno', 'tipo' => 'Tipo'];
    }

    public function columns(): array
    {
        return ['fecha' => 'Registrada', 'paciente' => 'Paciente', 'tipo' => 'Tipo', 'severidad' => 'Severidad', 'estado' => 'Estado'];
    }

    public function numeratorLabel(): string
    {
        return 'alergias';
    }

    public function groupsSmallCases(string $dimension): bool
    {
        return $dimension === 'alergeno';
    }

    public function feeds(): array
    {
        return ['alergias-clinicas-alerta-visual-expediente', 'prescripciones-alergias'];
    }

    public function items(Filters $f): array
    {
        if (! $f->started()) {
            return [];
        }

        return DB::connection('hospital')->table('allergies as a')
            ->join('local_patients as p', 'p.local_patient_id', '=', 'a.local_patient_id')
            ->whereBetween('a.recorded_at', $f->range())
            ->orderBy('a.recorded_at')->orderBy('a.allergy_id')
            ->get(['a.recorded_at', 'a.allergen_name', 'a.allergen_code', 'a.allergy_type', 'a.severity', 'a.status', 'p.code as patient'])
            ->map(function ($r) use ($f) {
                $severity = self::SEVERITY[$r->severity] ?? $r->severity;
                $type = self::TYPES[$r->allergy_type] ?? $r->allergy_type;
                $allergen = trim((string) ($r->allergen_name ?: $r->allergen_code));

                return ['peso' => 1, 'dims' => ['severidad' => $severity, 'alergeno' => mb_convert_case($allergen, MB_CASE_TITLE), 'tipo' => $type],
                    'fila' => ['fecha' => $f->localDateTime($r->recorded_at), 'paciente' => $r->patient, 'tipo' => $type, 'severidad' => $severity,
                        'estado' => self::STATUS[$r->status] ?? $r->status]];
            })->all();
    }
}

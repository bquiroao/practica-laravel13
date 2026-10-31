<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Ingresos hospitalarios: internamientos que empezaron en el período. Lo alimenta el módulo de admisión. */
class AdmissionIndicator extends Indicator
{
    public function key(): string
    {
        return 'ingresos';
    }

    public function name(): string
    {
        return 'Ingresos hospitalarios';
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
        return 'Pacientes que quedaron internados en el período.';
    }

    public function formula(): string
    {
        return 'número de internamientos iniciados en el período (sin los anulados)';
    }

    public function source(): string
    {
        return 'care_episodes (internamientos), bed_stays, wards, local_patients';
    }

    public function dimensions(): array
    {
        return ['sala' => 'Sala de ingreso', 'grupo_edad' => 'Grupo de edad', 'sexo' => 'Sexo'];
    }

    public function columns(): array
    {
        return ['paciente' => 'Paciente', 'ingreso' => 'Ingreso', 'sala' => 'Sala de ingreso', 'estado' => 'Situación actual'];
    }

    public function numeratorLabel(): string
    {
        return 'ingresos';
    }

    public function feeds(): array
    {
        return ['admision-hospitalaria-asignacion-cama'];
    }

    public function items(Filters $f): array
    {
        if (! $f->started()) {
            return [];
        }
        $db = DB::connection('hospital');
        $firstWard = $db->table('bed_stays as s')->join('beds as b', 'b.bed_id', '=', 's.bed_id')->join('wards as w', 'w.ward_id', '=', 'b.ward_id')
            ->whereColumn('s.care_episode_id', 'e.care_episode_id')->orderBy('s.started_at')->limit(1)->select('w.name');
        $sexes = ['female' => 'Femenino', 'male' => 'Masculino', 'other' => 'Otro', 'unknown' => 'No indicado'];

        return $db->table('care_episodes as e')
            ->join('local_patients as p', 'p.local_patient_id', '=', 'e.local_patient_id')
            ->where('e.type', 'inpatient')->where('e.status', '<>', 'cancelled')
            ->whereBetween('e.started_at', $f->range())
            ->orderBy('e.started_at')->orderBy('e.care_episode_id')
            ->select(['e.started_at', 'e.status', 'p.code as patient', 'p.birth_date', 'p.sex'])->selectSub($firstWard, 'ward')
            ->get()
            ->map(fn ($r) => [
                'peso' => 1,
                'dims' => ['sala' => $r->ward ?? 'Sin cama asignada', 'grupo_edad' => self::ageGroup(CarbonImmutable::parse($r->birth_date)->diffInYears(CarbonImmutable::parse($r->started_at))),
                    'sexo' => $sexes[$r->sex] ?? $r->sex],
                'fila' => ['paciente' => $r->patient, 'ingreso' => $f->localDateTime($r->started_at), 'sala' => $r->ward ?? 'Sin cama asignada',
                    'estado' => $r->status === 'open' ? 'Internado' : 'Egresado'],
            ])->all();
    }
}

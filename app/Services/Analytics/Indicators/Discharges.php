<?php

namespace App\Services\Analytics\Indicators;

use App\Models\Hospital\CareEpisode;
use App\Services\Analytics\Filters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Fuente común de los indicadores de egresos (egresos, estancia promedio y mortalidad): los internamientos que
 * terminaron en el período, con la sala de su última cama. Solo lectura.
 */
trait Discharges
{
    /** @return list<array{paciente: string, ingreso: string, egreso: string, dias: int, sala: string, tipo_alta: string, defuncion: bool, edad: int|null, fila: array<string, string|int|null>}> */
    protected function discharges(Filters $f): array
    {
        if (! $f->started()) {
            return [];
        }
        $db = DB::connection('hospital');
        $lastWard = $db->table('bed_stays as s')->join('beds as b', 'b.bed_id', '=', 's.bed_id')->join('wards as w', 'w.ward_id', '=', 'b.ward_id')
            ->whereColumn('s.care_episode_id', 'e.care_episode_id')->orderByDesc('s.started_at')->limit(1)->select('w.name');

        $rows = $db->table('care_episodes as e')
            ->join('local_patients as p', 'p.local_patient_id', '=', 'e.local_patient_id')
            ->where('e.type', 'inpatient')->where('e.status', 'closed')
            ->whereBetween('e.ended_at', $f->range())
            ->orderBy('e.ended_at')->orderBy('e.care_episode_id')
            ->select(['e.started_at', 'e.ended_at', 'e.discharge_type', 'p.code as patient', 'p.birth_date'])
            ->selectSub($lastWard, 'ward')
            ->get();

        return $rows->map(function ($r) use ($f) {
            $in = $f->localDate($r->started_at);
            $out = $f->localDate($r->ended_at);
            // Estancia en días de calendario; ingreso y egreso el mismo día cuenta como 1 día.
            $days = max(1, (int) CarbonImmutable::parse($in)->diffInDays(CarbonImmutable::parse($out)));
            $type = CareEpisode::DISCHARGE_TYPES[$r->discharge_type] ?? 'Sin tipo registrado';

            return [
                'dias' => $days, 'sala' => $r->ward ?? 'Sin cama asignada', 'tipo_alta' => $type, 'defuncion' => $r->discharge_type === 'defuncion',
                'edad' => $r->birth_date ? CarbonImmutable::parse($r->birth_date)->diffInYears(CarbonImmutable::parse($out)) : null,
                'fila' => ['paciente' => $r->patient, 'ingreso' => $f->localDateTime($r->started_at), 'egreso' => $f->localDateTime($r->ended_at),
                    'sala' => $r->ward ?? 'Sin cama asignada', 'tipo_alta' => $type, 'dias' => $days],
            ];
        })->all();
    }

    /** Mismo filtro que discharges(), solo el conteo. */
    protected function dischargeCount(Filters $f): int
    {
        return $f->started() ? DB::connection('hospital')->table('care_episodes')->where('type', 'inpatient')->where('status', 'closed')
            ->whereBetween('ended_at', $f->range())->count() : 0;
    }

    protected function dischargeColumns(): array
    {
        return ['paciente' => 'Paciente', 'ingreso' => 'Ingreso', 'egreso' => 'Egreso', 'sala' => 'Última sala', 'tipo_alta' => 'Tipo de egreso', 'dias' => 'Días de estancia'];
    }
}

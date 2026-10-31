<?php

namespace App\Services\Analytics\Indicators;

use App\Models\Hospital\Bed;
use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ocupación de camas del período: días-cama ocupados ÷ días-cama disponibles × 100.
 * Un día-cama ocupado es una cama con estancia abierta al cierre de ese día (censo de medianoche; hoy, al momento
 * de la consulta). Por eso una estancia que empieza y termina el mismo día no suma días.
 */
class OccupancyIndicator extends Indicator
{
    public function key(): string
    {
        return 'ocupacion';
    }

    public function name(): string
    {
        return 'Ocupación de camas';
    }

    public function area(): string
    {
        return 'Hospitalización';
    }

    public function unit(): string
    {
        return '%';
    }

    public function description(): string
    {
        return 'Qué parte de la capacidad de camas se usó en el período.';
    }

    public function formula(): string
    {
        return 'días-cama ocupados / días-cama disponibles × 100';
    }

    public function source(): string
    {
        return 'wards, beds, bed_stays, care_episodes, local_patients';
    }

    public function dimensions(): array
    {
        return ['sala' => 'Sala', 'tipo_cama' => 'Tipo de cama'];
    }

    public function columns(): array
    {
        return ['cama' => 'Cama', 'sala' => 'Sala', 'paciente' => 'Paciente', 'ingreso' => 'En la cama desde', 'egreso' => 'Hasta', 'peso' => 'Días-cama'];
    }

    public function numeratorLabel(): string
    {
        return 'días-cama ocupados';
    }

    public function denominatorLabel(): string
    {
        return 'días-cama disponibles';
    }

    public function population(array $items, ?int $denominator): int
    {
        return count($items);
    }

    public function note(): string
    {
        return 'Las camas disponibles son las que hoy no están inactivas, más las que tuvieron pacientes en el período.';
    }

    public function feeds(): array
    {
        return ['catalogo-salas-camas', 'admision-hospitalaria-asignacion-cama', 'traslados-altas-camas'];
    }

    public function items(Filters $f): array
    {
        if (! $f->started()) {
            return [];
        }
        [$from, $to] = $f->range();
        $today = $f->now->toDateString();
        $first = $f->from->toDateString();
        $last = $f->end()->toDateString();

        $rows = DB::connection('hospital')->table('bed_stays as s')
            ->join('beds as b', 'b.bed_id', '=', 's.bed_id')
            ->join('wards as w', 'w.ward_id', '=', 'b.ward_id')
            ->join('care_episodes as e', 'e.care_episode_id', '=', 's.care_episode_id')
            ->join('local_patients as p', 'p.local_patient_id', '=', 'e.local_patient_id')
            ->where('s.started_at', '<=', $to)
            ->where(fn ($q) => $q->whereNull('s.ended_at')->orWhere('s.ended_at', '>=', $from))
            ->orderBy('s.started_at')->orderBy('s.bed_stay_id')
            ->get(['s.bed_id', 's.started_at', 's.ended_at', 'b.code as bed', 'b.type', 'w.code as ward_code', 'w.name as ward', 'p.code as patient']);

        $items = [];
        foreach ($rows as $r) {
            // Días con la cama ocupada al cierre: desde el día del ingreso hasta el día anterior al egreso (o hoy si sigue abierta).
            $start = max($first, $f->localDate($r->started_at));
            $end = min($last, $r->ended_at === null ? $today : CarbonImmutable::parse($f->localDate($r->ended_at))->subDay()->toDateString());
            if ($end < $start) {
                continue;
            }
            $days = (int) CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1;
            $items[] = [
                'peso' => $days,
                'dims' => ['sala' => $r->ward, 'tipo_cama' => Bed::TYPES[$r->type] ?? $r->type],
                'fila' => ['cama' => $r->ward_code.' / '.$r->bed, 'sala' => $r->ward, 'paciente' => $r->patient, 'ingreso' => $f->localDateTime($r->started_at),
                    'egreso' => $r->ended_at === null ? null : $f->localDateTime($r->ended_at), 'peso' => $days],
                'bed_id' => $r->bed_id,
            ];
        }

        return $items;
    }

    public function denominator(Filters $f, array $items): int
    {
        $operational = DB::connection('hospital')->table('beds')->where('status', '<>', 'inactive')->pluck('bed_id')->all();
        $beds = count(array_unique(array_merge($operational, array_column($items, 'bed_id'))));

        return $beds * $f->elapsedDays();
    }
}

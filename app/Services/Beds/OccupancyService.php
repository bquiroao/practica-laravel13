<?php

namespace App\Services\Beds;

use App\Models\Hospital\Bed;
use App\Models\Hospital\Ward;

/**
 * Módulo dashboard de ocupación: por sala, camas por estado y porcentaje de ocupación sobre las camas operativas
 * (las que no están inactivas ni en mantenimiento). «Ocupada» se cuenta por la estancia abierta; si el estado
 * guardado de la cama no coincide, se marca como inconsistente para que alguien lo revise.
 */
class OccupancyService
{
    /** @return array{totales: array<string, mixed>, salas: list<array<string, mixed>>} */
    public function snapshot(): array
    {
        $wards = Ward::query()->where('status', 'active')->orderBy('name')
            ->with(['beds' => fn ($q) => $q->with('openStay.episode.patient')])->get();

        $rows = $wards->map(function (Ward $ward): array {
            $counts = ['available' => 0, 'occupied' => 0, 'maintenance' => 0, 'inactive' => 0];
            $inconsistent = 0;
            foreach ($ward->beds as $bed) {
                $real = $bed->openStay ? 'occupied' : ($bed->status === 'occupied' ? 'available' : $bed->status);
                $counts[$real]++;
                if (($bed->openStay !== null) !== ($bed->status === 'occupied')) {
                    $inconsistent++;
                }
            }

            return ['id' => $ward->ward_id, 'code' => $ward->code, 'name' => $ward->name, 'location' => $ward->location, 'capacity' => $ward->capacity]
                + $this->summary($counts) + ['inconsistentes' => $inconsistent];
        })->values();

        $total = ['available' => $rows->sum('available'), 'occupied' => $rows->sum('occupied'), 'maintenance' => $rows->sum('maintenance'), 'inactive' => $rows->sum('inactive')];

        return [
            'totales' => $this->summary($total) + ['inconsistentes' => $rows->sum('inconsistentes')],
            'salas' => $rows->all(),
            'generado_en' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, int>  $c
     * @return array<string, mixed>
     */
    private function summary(array $c): array
    {
        $operative = $c['available'] + $c['occupied'];

        return $c + ['total' => array_sum($c), 'operativas' => $operative, 'ocupacion' => $operative === 0 ? null : round(100 * $c['occupied'] / $operative, 1)];
    }

    /** Estado que se muestra de una cama: la estancia abierta manda sobre el estado guardado. */
    public static function displayStatus(Bed $bed): string
    {
        return $bed->openStay ? 'occupied' : ($bed->status === 'occupied' ? 'available' : $bed->status);
    }
}

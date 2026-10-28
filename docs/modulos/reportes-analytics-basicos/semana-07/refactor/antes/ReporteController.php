<?php
// ANTES (acoplado): SQL, cálculo, redondeo y texto para la vista en un solo método.
final class ReporteController
{
    public function ocupacion(Request $request): JsonResponse
    {
        $habilitadas = DB::table('bed')->where('enabled', true)->count();
        $porSala = DB::table('bed_stay')
            ->join('bed', 'bed.id', '=', 'bed_stay.bed_id')
            ->join('ward', 'ward.id', '=', 'bed.ward_id')
            ->whereBetween('bed_stay.started_at', [$request->desde, $request->hasta])   // además ignora estancias que empezaron antes
            ->groupBy('ward.name')
            ->selectRaw('ward.name, count(distinct bed.id) as ocupadas')
            ->pluck('ocupadas', 'name');

        $ocupadas = $porSala->sum();
        return response()->json([
            'valor' => round($ocupadas / $habilitadas * 100) . '%',                    // texto, sin decimales
            'desglose' => $porSala->map(fn ($n) => round($n / $ocupadas * 100) . '%'),  // porcentajes redondeados
        ]);
    }
}

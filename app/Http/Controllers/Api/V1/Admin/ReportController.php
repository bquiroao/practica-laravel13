<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditTrail;
use App\Services\Reports\OperationalReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Módulo reportes operativos exportables: vista previa y exportación auditada (filtros, fecha de corte y hash). */
class ReportController extends Controller
{
    public function __construct(private readonly OperationalReportService $reports) {}

    public function types(): JsonResponse
    {
        return response()->json(['data' => collect(OperationalReportService::TYPES)->map(fn ($t, $k) => ['key' => $k, 'title' => $t])->values()]);
    }

    public function preview(Request $request): JsonResponse
    {
        $report = $this->build($request);

        return response()->json($report + ['total' => count($report['rows'])]);
    }

    public function export(Request $request, AuditTrail $audit): Response
    {
        $report = $this->build($request);
        $csv = $this->reports->csv($report);
        $hash = hash('sha256', $csv);
        $audit->record('reportes', 'REPORTE_EXPORTADO', 'exito', $request->attributes->get('hospital')->hospital_id, $request->user()->user_id, 'reporte:'.$report['type'],
            ['desde' => $report['from'], 'hasta' => $report['to'], 'corte' => $report['cutoff'], 'filas' => count($report['rows']), 'sha256' => $hash], $request->ip());

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$report['type'].'_'.$report['from'].'_'.$report['to'].'.csv"',
            'X-Report-Sha256' => $hash,
            'Access-Control-Expose-Headers' => 'X-Report-Sha256, Content-Disposition',
        ]);
    }

    /** @return array<string, mixed> */
    private function build(Request $request): array
    {
        $data = $request->validate([
            'tipo' => ['required', 'string'], 'desde' => ['required', 'date_format:Y-m-d'], 'hasta' => ['required', 'date_format:Y-m-d'],
        ], [], ['tipo' => 'reporte', 'desde' => 'desde', 'hasta' => 'hasta']);
        $tz = $request->attributes->get('hospital')->timezone ?: config('app.timezone');

        return $this->reports->build($data['tipo'], $data['desde'], $data['hasta'], $tz);
    }
}

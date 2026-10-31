<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Quality\QualityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Módulos QA de regresión y defectos, y QA E2E/CI: defectos, ejecuciones por versión y quality gate. */
class QualityController extends Controller
{
    public function __construct(private readonly QualityService $quality) {}

    public function defects(Request $request): JsonResponse
    {
        $request->validate(['estado' => ['nullable', 'string']]);
        $rows = DB::connection('central')->table('qa_defects')
            ->when($request->query('estado') === 'abiertos', fn ($q) => $q->where('status', '<>', 'cerrado'))
            ->orderByRaw("CASE severity WHEN 'critica' THEN 0 WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END")->orderByDesc('created_at')->get();

        return response()->json(['data' => $rows->map(fn ($d) => (array) $d + ['next' => QualityService::TRANSITIONS[$d->status] ?? []])]);
    }

    public function storeDefect(Request $request): JsonResponse
    {
        $modules = array_map('basename', glob(base_path('docs/modulos/*'), GLOB_ONLYDIR));
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200', 'regex:/\S/'], 'module' => ['required', Rule::in($modules)],
            'severity' => ['required', Rule::in(['critica', 'alta', 'media', 'baja'])], 'steps' => ['required', 'string', 'max:3000', 'regex:/\S/'],
            'version' => ['nullable', 'string', 'max:40'],
        ], [], ['title' => 'título', 'module' => 'módulo', 'severity' => 'severidad', 'steps' => 'pasos para reproducirlo', 'version' => 'versión']);

        return response()->json(['data' => $this->quality->report($request->user(), $request->attributes->get('hospital'), $data, $request->ip())], 201);
    }

    public function transitionDefect(Request $request, string $defect): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'string'], 'note' => ['nullable', 'string', 'max:2000']]);

        return response()->json(['data' => $this->quality->transition($request->user(), $request->attributes->get('hospital'), $defect, $data['status'], (string) ($data['note'] ?? ''), $request->ip())]);
    }

    public function runs(): JsonResponse
    {
        $runs = DB::connection('central')->table('qa_runs')->orderByDesc('ran_at')->limit(50)->get();
        $versions = $runs->pluck('version')->unique()->take(5)->values();

        return response()->json(['data' => $runs, 'gates' => $versions->map(fn ($v) => $this->quality->gate($v))]);
    }

    public function storeRun(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->quality->registerRun($request->user(), $request->attributes->get('hospital'), $this->runData($request), 'manual', $request->ip())], 201);
    }

    /** Lo llama el pipeline de CI con NIDO_CI_TOKEN (sin sesión de usuario). */
    public function storeCiRun(Request $request): JsonResponse
    {
        $token = (string) config('nido.ci_token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->bearerToken()), 401, 'Token de CI inválido.');

        return response()->json(['data' => $this->quality->registerRun(null, null, $this->runData($request), 'ci', $request->ip())], 201);
    }

    public function gate(string $version): JsonResponse
    {
        return response()->json($this->quality->gate($version));
    }

    /** @return array<string, mixed> */
    private function runData(Request $request): array
    {
        return $request->validate([
            'version' => ['required', 'string', 'max:40', 'regex:/\S/'], 'suite' => ['required', 'string', 'max:60', 'regex:/\S/'],
            'total' => ['required', 'integer', 'min:1'], 'passed' => ['required', 'integer', 'min:0'], 'failed' => ['required', 'integer', 'min:0'],
            'flaky' => ['nullable', 'integer', 'min:0'], 'commit_sha' => ['nullable', 'string', 'max:64'],
        ], [], ['version' => 'versión', 'suite' => 'suite', 'total' => 'total', 'passed' => 'aprobadas', 'failed' => 'fallidas', 'flaky' => 'inestables']);
    }
}

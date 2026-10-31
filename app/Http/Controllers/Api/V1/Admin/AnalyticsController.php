<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\Filters;
use App\Services\Analytics\IndicatorRegistry;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Módulo reportes y analytics básicos: indicadores trazables en tres niveles (solo GET, RNF-04).
 * Cada respuesta lleva X-Correlation-Id y queda en el log con su duración, para medir RNF-01 (menos de 2 s).
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    /** Indicadores que el usuario puede consultar y los que esperan a otros módulos. */
    public function catalog(Request $request): JsonResponse
    {
        return $this->respond($request, fn () => [
            'data' => array_map(fn ($i) => $this->analytics->meta($i), $this->analytics->allowed($request->user(), $this->hospital($request))),
            'pendientes' => $this->analytics->seesAll($request->user(), $this->hospital($request)) ? IndicatorRegistry::PENDING : [],
            'granularidades' => Filters::GRANULARITIES,
            'max_dias' => Filters::MAX_DAYS,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $data = $this->period($request);

        return $this->respond($request, fn () => ['data' => $this->analytics->summary($request->user(), $this->hospital($request), $data['desde'], $data['hasta'])]);
    }

    public function show(Request $request, string $tipo): JsonResponse
    {
        $data = $this->period($request, ['granularidad' => ['nullable', Rule::in(array_keys(Filters::GRANULARITIES))]]);

        return $this->respond($request, function () use ($request, $tipo, $data) {
            [$indicator, $filters] = $this->resolve($request, $tipo, $data);

            return $this->analytics->level1($indicator, $filters, $data['granularidad'] ?? null);
        });
    }

    public function breakdown(Request $request, string $tipo): JsonResponse
    {
        $data = $this->period($request, ['dimension' => ['nullable', 'string', 'max:40']]);

        return $this->respond($request, function () use ($request, $tipo, $data) {
            [$indicator, $filters] = $this->resolve($request, $tipo, $data);

            return $this->analytics->level2($indicator, $filters, $data['dimension'] ?? null);
        });
    }

    public function records(Request $request, string $tipo): JsonResponse
    {
        $data = $this->period($request, [
            'dimension' => ['nullable', 'string', 'max:40'],
            'parte' => ['required', 'string', 'max:200'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->respond($request, function () use ($request, $tipo, $data) {
            [$indicator, $filters] = $this->resolve($request, $tipo, $data);

            return $this->analytics->level3($indicator, $filters, $data['dimension'] ?? null, $data['parte'], (int) ($data['pagina'] ?? 1), (int) ($data['limite'] ?? 10));
        });
    }

    /** RN-02: período obligatorio con fechas AAAA-MM-DD. El orden y el largo los valida Filters. */
    private function period(Request $request, array $rules = []): array
    {
        return $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d'],
            'medico' => ['nullable', 'uuid'],
        ] + $rules, [
            'date_format' => 'Escriba la fecha «:attribute» como AAAA-MM-DD, por ejemplo 2026-10-01.',
            'parte.required' => 'Indique qué parte del desglose quiere rastrear.',
        ], ['desde' => 'desde', 'hasta' => 'hasta', 'medico' => 'médico', 'granularidad' => 'granularidad', 'dimension' => 'dimensión', 'parte' => 'parte',
            'pagina' => 'página', 'limite' => 'límite']);
    }

    private function resolve(Request $request, string $tipo, array $data): array
    {
        $indicator = $this->analytics->indicator($request->user(), $this->hospital($request), $tipo);

        return [$indicator, $this->analytics->filters($request->user(), $this->hospital($request), $indicator, $data['desde'], $data['hasta'], $data['medico'] ?? null)];
    }

    private function hospital(Request $request)
    {
        return $request->attributes->get('hospital');
    }

    /** @param  Closure(): array  $work */
    private function respond(Request $request, Closure $work): JsonResponse
    {
        $correlation = Str::limit((string) $request->header('X-Correlation-Id'), 64, '') ?: (string) Str::uuid();
        $started = hrtime(true);
        $status = 200;
        try {
            $body = $work();
        } catch (DomainRuleException $e) {
            $status = $e->status;
            $response = $e->render();
        } catch (QueryException $e) {
            // El detalle técnico (SQLSTATE, host, puerto) va al log, nunca al usuario.
            Log::error('reportes.analytics.fuente', ['correlationId' => $correlation, 'error' => $e->getMessage()]);
            $status = 503;
            $response = response()->json(['message' => 'No se pudieron obtener los datos en este momento. Intente de nuevo en unos minutos.',
                'code' => 'DATOS_NO_DISPONIBLES', 'details' => ['correlationId' => $correlation]], 503);
        }
        Log::info('reportes.analytics', ['correlationId' => $correlation, 'ruta' => $request->path(), 'estado' => $status,
            'ms' => round((hrtime(true) - $started) / 1e6, 1)]);

        return ($response ?? response()->json($body))->header('X-Correlation-Id', $correlation);
    }
}

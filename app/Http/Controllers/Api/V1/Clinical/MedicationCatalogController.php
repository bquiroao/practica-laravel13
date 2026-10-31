<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\MedicationRequest;
use App\Models\Hospital\Medication;
use App\Services\Medications\MedicationCatalogService;
use App\Services\Medications\MedicationOptions;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Módulo catálogo de medicamentos. Toda respuesta lleva X-Request-Id (RNF-05), también los errores; si la base del
 * hospital no responde, el error es 503 PERSISTENCIA con Retry-After y sin detalles internos (corrige D-02).
 */
class MedicationCatalogController extends Controller implements HasMiddleware
{
    public function __construct(private readonly MedicationCatalogService $catalog) {}

    public static function middleware(): array
    {
        return [new Middleware(function (Request $request, Closure $next) {
            $given = (string) $request->header('X-Request-Id');
            $id = preg_match('/^[A-Za-z0-9-]{8,64}$/', $given) ? $given : (string) Str::uuid();
            $response = $next($request);
            $e = $response->exception ?? null;
            if ($e instanceof QueryException && (str_starts_with((string) $e->getCode(), '08') || str_contains($e->getMessage(), 'SQLSTATE[08'))) {
                $response = response()->json(['message' => 'El catálogo no está disponible en este momento. Intente de nuevo en unos segundos.',
                    'code' => 'PERSISTENCIA', 'details' => ['request_id' => $id]], 503)->header('Retry-After', '5');
            }

            return $response->header('X-Request-Id', $id);
        })];
    }

    /** Listas cerradas para los formularios y filtros. */
    public function options(): JsonResponse
    {
        return response()->json(['data' => MedicationOptions::all()]);
    }

    /** CU-02: vigentes en una fecha (hoy si no se indica). */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'min:2', 'max:60'],
            'forma' => ['nullable', 'string', Rule::in(array_keys(MedicationOptions::FORMS))],
            'fecha' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2099-12-31'],
        ], ['q.min' => 'Escriba al menos 2 letras para buscar.'], ['q' => 'texto de búsqueda', 'forma' => 'forma farmacéutica', 'fecha' => 'fecha']);
        $date = $data['fecha'] ?? $this->today($request);
        $result = $this->catalog->search($data['q'] ?? null, $data['forma'] ?? null, $date);

        return response()->json(['data' => $result['items']->map(fn (Medication $m) => $this->present($m, $date)),
            'meta' => ['total' => $result['total'], 'fecha' => $date, 'limite' => MedicationOptions::MAX_RESULTS]]);
    }

    /** RF-09: la versión vigente del código en la fecha; si no hay ninguna, la última, para mostrar su estado. */
    public function show(Request $request, string $code): JsonResponse
    {
        $data = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']], [], ['fecha' => 'fecha']);
        $date = $data['fecha'] ?? $this->today($request);
        $versions = $this->catalog->history($code);
        $shown = $versions->first(fn (Medication $m) => $m->valid_from->toDateString() <= $date && ($m->valid_to === null || $m->valid_to->toDateString() > $date));
        $uses = $this->catalog->usage($versions->last());

        return response()->json(['data' => $this->present($shown ?? $versions->last(), $date),
            'meta' => ['fecha' => $date, 'vigente' => $shown !== null, 'versiones' => $versions->count(), 'ultima_version' => $versions->last()->version,
                'abierto' => $versions->last()->valid_to === null, 'recetas' => $uses, 'puede_eliminarse' => $uses === 0]]);
    }

    /** CU-05: historial de versiones, de la más antigua a la más reciente. */
    public function history(Request $request, string $code): JsonResponse
    {
        $date = $this->today($request);

        return response()->json(['data' => $this->catalog->history($code)->map(fn (Medication $m) => $this->present($m, $date))]);
    }

    /** CU-01: alta. */
    public function store(MedicationRequest $request): JsonResponse
    {
        $m = $this->catalog->register($request->user(), $request->attributes->get('hospital'), $request->validated(), $request->ip());

        return response()->json(['data' => $this->present($m, $this->today($request))], 201)->header('Location', "/api/v1/catalogo/medicamentos/{$m->code}");
    }

    /** CU-03: versión nueva; `version` es la versión que el usuario estaba viendo. */
    public function storeVersion(MedicationRequest $request, string $code): JsonResponse
    {
        $m = $this->catalog->newVersion($request->user(), $request->attributes->get('hospital'), $code, $request->validated(), $request->ip());

        return response()->json(['data' => $this->present($m, $this->today($request))], 201)->header('Location', "/api/v1/catalogo/medicamentos/{$m->code}");
    }

    /** CU-04: baja con fecha y motivo. */
    public function retire(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:2099-12-31'],
            'reason' => ['required', 'string', 'min:5', 'max:200', 'regex:/\p{L}/u'],
            'version' => ['nullable', 'integer', 'between:1,999'],
        ], ['reason.min' => 'Escriba el motivo de la baja (al menos 5 letras), p. ej. «Retiro del formulario».'], ['date' => 'fecha de baja', 'reason' => 'motivo', 'version' => 'versión']);
        $m = $this->catalog->retire($request->user(), $request->attributes->get('hospital'), $code, $data['date'] ?? null, $data['reason'], $data['version'] ?? null, $request->ip());

        return response()->json(['data' => $this->present($m, $this->today($request))]);
    }

    /** CU-06: eliminar un registro erróneo que nunca se usó. */
    public function destroy(Request $request, string $code): Response
    {
        $this->catalog->delete($request->user(), $request->attributes->get('hospital'), $code, $request->ip());

        return response()->noContent();
    }

    private function today(Request $request): string
    {
        return MedicationCatalogService::today($request->attributes->get('hospital'));
    }

    /** @return array<string, mixed> */
    private function present(Medication $m, string $date): array
    {
        $from = $m->valid_from->toDateString();
        $to = $m->valid_to?->toDateString();
        $state = match (true) {
            $from > $date => 'programada',
            $to !== null && $to <= $date => $m->close_reason !== null ? 'baja' : 'reemplazada',
            default => 'vigente',
        };
        $strength = rtrim(rtrim((string) $m->strength_value, '0'), '.');

        return [
            'id' => $m->medication_id, 'code' => $m->code, 'version' => $m->version, 'name' => $m->name, 'active_ingredient' => $m->active_ingredient,
            'strength_value' => $strength, 'strength_unit' => $m->strength_unit, 'strength' => "{$strength} {$m->strength_unit}",
            'dosage_form' => $m->dosage_form, 'dosage_form_label' => MedicationOptions::FORMS[$m->dosage_form],
            'route' => $m->route, 'route_label' => MedicationOptions::ROUTES[$m->route], 'presentation' => $m->presentation,
            'sanitary_registration' => $m->sanitary_registration, 'therapeutic_group' => $m->therapeutic_group,
            'therapeutic_group_label' => MedicationOptions::GROUPS[$m->therapeutic_group], 'allergy_groups' => $m->allergy_groups,
            'legacy_code' => $m->legacy_code, 'valid_from' => $from, 'valid_to' => $to, 'close_reason' => $m->close_reason, 'state' => $state,
            'created_by' => $m->relationLoaded('creator') ? ($m->creator?->display_name ?? 'Carga inicial') : null,
            'closed_by' => $m->relationLoaded('closer') ? $m->closer?->display_name : null,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}

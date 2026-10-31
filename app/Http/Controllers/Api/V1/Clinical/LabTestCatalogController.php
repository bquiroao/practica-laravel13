<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\LabTestRequest;
use App\Models\Hospital\LabTest;
use App\Services\LabCatalog\LabTestCatalogService;
use App\Services\LabCatalog\LabTestOptions;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Módulo catálogo de pruebas de laboratorio. Toda respuesta lleva X-Request-Id, y cada petición deja en el registro
 * su identificador, la ruta y el estado, sin datos clínicos (RNF-06).
 */
class LabTestCatalogController extends Controller implements HasMiddleware
{
    public function __construct(private readonly LabTestCatalogService $catalog) {}

    public static function middleware(): array
    {
        return [new Middleware(function (Request $request, Closure $next) {
            $given = (string) $request->header('X-Request-Id');
            $id = preg_match('/^[A-Za-z0-9-]{8,64}$/', $given) ? $given : (string) Str::uuid();
            $request->attributes->set('request_id', $id);
            $response = $next($request);
            Log::info('catalogo-lab', ['request_id' => $id, 'ruta' => $request->method().' '.$request->path(), 'estado' => $response->getStatusCode()]);

            return $response->header('X-Request-Id', $id);
        })];
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => LabTestOptions::all()]);
    }

    /** CU-04: pruebas vigentes en una fecha (hoy si no se indica). */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'min:2', 'max:60'],
            'seccion' => ['nullable', 'string', Rule::in(array_keys(LabTestOptions::SECTIONS))],
            'muestra' => ['nullable', 'string', Rule::in(array_keys(LabTestOptions::SPECIMENS))],
            'fecha' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2099-12-31'],
        ], ['q.min' => 'Escriba al menos 2 letras para buscar.'], ['q' => 'texto de búsqueda', 'seccion' => 'sección', 'muestra' => 'tipo de muestra', 'fecha' => 'fecha']);
        $date = $data['fecha'] ?? $this->today($request);
        $result = $this->catalog->search($data['q'] ?? null, $data['seccion'] ?? null, $data['muestra'] ?? null, $date);

        return response()->json(['data' => $result['items']->map(fn (LabTest $t) => $this->present($t, $date)),
            'meta' => ['total' => $result['total'], 'fecha' => $date, 'limite' => LabTestOptions::MAX_RESULTS]]);
    }

    /** CU-05 y RN-06: detalle de la versión vigente; si no hay ninguna en esa fecha, 404 con su vigencia. */
    public function show(Request $request, string $code): JsonResponse
    {
        $data = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']], [], ['fecha' => 'fecha']);
        $date = $data['fecha'] ?? $this->today($request);
        $versions = $this->catalog->history($code);
        $test = $versions->first(fn (LabTest $t) => $t->valid_from->toDateString() <= $date && ($t->valid_to === null || $t->valid_to->toDateString() >= $date));
        if ($test === null) {
            $next = $versions->first(fn (LabTest $t) => $t->valid_from->toDateString() > $date);
            $last = $versions->last();
            throw new DomainRuleException('PRUEBA_NO_VIGENTE', $next
                ? "{$last->code} no está vigente el {$date}: es vigente desde el {$next->valid_from->toDateString()}."
                : "{$last->code} no está vigente el {$date}: fue vigente hasta el {$last->valid_to?->toDateString()}.", 404,
                ['vigencia' => ['desde' => ($next ?? $last)->valid_from->toDateString(), 'hasta' => ($next ?? $last)->valid_to?->toDateString()]]);
        }

        return response()->json(['data' => $this->present($test, $date), 'meta' => ['fecha' => $date, 'versiones' => $versions->count(), 'ultima_version' => $versions->last()->version]]);
    }

    /** Historial de versiones, de la más antigua a la más reciente (RN-08: quién registró cada una), con su estado en la fecha. */
    public function history(Request $request, string $code): JsonResponse
    {
        $data = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']], [], ['fecha' => 'fecha']);
        $date = $data['fecha'] ?? $this->today($request);

        return response()->json(['data' => $this->catalog->history($code)->map(fn (LabTest $t) => $this->present($t, $date))]);
    }

    /** CU-01 a CU-03: alta. */
    public function store(LabTestRequest $request): JsonResponse
    {
        $test = $this->catalog->register($request->user(), $request->attributes->get('hospital'), $request->validated(), $request->ip());

        return response()->json(['data' => $this->present($test, $this->today($request))], 201)->header('Location', "/api/v1/catalogo/pruebas-laboratorio/{$test->code}");
    }

    /** RN-05: versión nueva; `version` es la que el usuario estaba viendo. */
    public function storeVersion(LabTestRequest $request, string $code): JsonResponse
    {
        $test = $this->catalog->newVersion($request->user(), $request->attributes->get('hospital'), $code, $request->validated(), $request->ip());

        return response()->json(['data' => $this->present($test, $this->today($request))], 201)->header('Location', "/api/v1/catalogo/pruebas-laboratorio/{$test->code}");
    }

    private function today(Request $request): string
    {
        return LabTestCatalogService::today($request->attributes->get('hospital'));
    }

    /** @return array<string, mixed> */
    private function present(LabTest $t, string $date): array
    {
        $from = $t->valid_from->toDateString();
        $to = $t->valid_to?->toDateString();
        $state = $from > $date ? 'programada' : ($to !== null && $to < $date ? 'cerrada' : 'vigente');
        $daysLeft = $state === 'vigente' && $to !== null ? (int) CarbonImmutable::parse($date)->diffInDays(CarbonImmutable::parse($to)) : null;
        $number = fn ($v) => $v === null ? null : (float) $v;

        return [
            'id' => $t->lab_test_id, 'code' => $t->code, 'version' => $t->version, 'name' => $t->name,
            'section' => $t->section, 'section_label' => LabTestOptions::SECTIONS[$t->section], 'turnaround_hours' => $t->turnaround_hours,
            'specimen_type' => $t->specimen_type, 'specimen_label' => LabTestOptions::SPECIMENS[$t->specimen_type], 'container' => $t->container,
            'min_volume_ml' => $number($t->min_volume_ml), 'fasting_hours' => $t->fasting_hours, 'preparation_instructions' => $t->preparation_instructions,
            'storage' => $t->storage, 'storage_label' => LabTestOptions::STORAGE[$t->storage], 'stability_hours' => $t->stability_hours,
            'unit' => $t->unit, 'ref_low' => $number($t->ref_low), 'ref_high' => $number($t->ref_high),
            'critical_low' => $number($t->critical_low), 'critical_high' => $number($t->critical_high),
            'valid_from' => $from, 'valid_to' => $to, 'state' => $state,
            // Cambio de la semana 12: cuántos días le quedan, para avisar «vence pronto» (el servidor decide si está vigente).
            'expires_in_days' => $daysLeft, 'expiring' => $daysLeft !== null && $daysLeft <= LabTestOptions::EXPIRING_DAYS,
            'initial_load' => $t->initial_load,
            'created_by' => $t->relationLoaded('creator') ? ($t->creator?->display_name ?? 'Carga inicial') : null,
            'closed_by' => $t->relationLoaded('closer') ? $t->closer?->display_name : null,
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }
}

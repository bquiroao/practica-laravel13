<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Http\Controllers\Controller;
use App\Models\Hospital\Bed;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\Ward;
use App\Services\Beds\BedCatalogService;
use App\Services\Beds\BedMovementService;
use App\Services\Beds\OccupancyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Módulos catálogo de salas y camas, traslados y altas, y dashboard de ocupación. */
class BedController extends Controller
{
    public function __construct(private readonly BedCatalogService $catalog, private readonly BedMovementService $movements) {}

    /** Mapa de camas: salas con sus camas, el estado real de cada una y, si está ocupada, quién la ocupa. */
    public function index(Request $request): JsonResponse
    {
        $wards = Ward::query()->when(! $request->boolean('todas'), fn ($q) => $q->where('status', 'active'))->orderBy('name')
            ->with(['beds' => fn ($q) => $q->with('openStay.episode.patient')])->get();

        return response()->json(['data' => $wards->map(fn (Ward $w) => [
            'id' => $w->ward_id, 'code' => $w->code, 'name' => $w->name, 'location' => $w->location, 'capacity' => $w->capacity, 'status' => $w->status,
            'beds' => $w->beds->map(fn (Bed $b) => $this->bed($b))->values(),
        ])]);
    }

    public function storeWard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'name' => ['required', 'string', 'max:80', 'regex:/\S/'],
            'location' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ], ['code.regex' => 'El código solo lleva letras, números y guiones (p. ej. MED-1).'], ['code' => 'código', 'name' => 'nombre', 'location' => 'ubicación', 'capacity' => 'capacidad']);
        $ward = $this->catalog->createWard($request->user(), $request->attributes->get('hospital'), $data['code'], $data['name'], $data['location'], $data['capacity'] ?? null, $request->ip());

        return response()->json(['data' => ['id' => $ward->ward_id, 'code' => $ward->code, 'name' => $ward->name]], 201);
    }

    public function storeBed(Request $request, string $ward): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'type' => ['required', Rule::in(array_keys(Bed::TYPES))],
        ], ['code.regex' => 'El código solo lleva letras, números y guiones (p. ej. 101-A).'], ['code' => 'código', 'type' => 'tipo']);
        $bed = $this->catalog->createBed($request->user(), $request->attributes->get('hospital'), $ward, $data['code'], $data['type'], $request->ip());

        return response()->json(['data' => $this->bed($bed->load('openStay'))], 201);
    }

    public function updateBedStatus(Request $request, string $bed): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['available', 'maintenance', 'inactive'])], 'reason' => ['nullable', 'string', 'max:300']], [], ['status' => 'estado', 'reason' => 'motivo']);
        $model = Bed::query()->find($bed) ?? abort(404, 'Cama no encontrada.');
        $updated = $this->catalog->changeStatus($request->user(), $request->attributes->get('hospital'), $model, $data['status'], (string) ($data['reason'] ?? ''), $request->ip());

        return response()->json(['data' => $this->bed($updated->load('openStay'))]);
    }

    public function transfer(Request $request, string $episode): JsonResponse
    {
        $data = $request->validate(['bed_id' => ['required', 'uuid'], 'reason' => ['nullable', 'string', 'max:300'], 'require_icu' => ['sometimes', 'boolean']], [], ['bed_id' => 'cama destino', 'reason' => 'motivo']);
        $stay = $this->movements->transfer($request->user(), $request->attributes->get('hospital'), $episode, $data['bed_id'], (string) ($data['reason'] ?? ''), (bool) ($data['require_icu'] ?? false), $request->ip());

        return response()->json(['data' => ['bed_stay_id' => $stay->bed_stay_id, 'bed_id' => $stay->bed_id]]);
    }

    public function discharge(Request $request, string $episode): JsonResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(CareEpisode::DISCHARGE_TYPES))], 'reason' => ['nullable', 'string', 'max:1000']], [], ['type' => 'tipo de alta', 'reason' => 'motivo']);
        $closed = $this->movements->discharge($request->user(), $request->attributes->get('hospital'), $episode, $data['type'], (string) ($data['reason'] ?? ''), $request->ip());

        return response()->json(['data' => ['care_episode_id' => $closed->care_episode_id, 'status' => $closed->status, 'discharge_type' => $closed->discharge_type]]);
    }

    public function occupancy(OccupancyService $occupancy): JsonResponse
    {
        return response()->json($occupancy->snapshot());
    }

    /** @return array<string, mixed> */
    private function bed(Bed $b): array
    {
        $stay = $b->openStay;

        return [
            'id' => $b->bed_id, 'code' => $b->code, 'type' => $b->type, 'status' => OccupancyService::displayStatus($b),
            'status_reason' => $b->status_reason, 'next' => $stay ? [] : (Bed::TRANSITIONS[$b->status === 'occupied' ? 'available' : $b->status] ?? []),
            'occupant' => $stay ? [
                'episode_id' => $stay->care_episode_id, 'since' => $stay->started_at->toIso8601String(), 'reason' => $stay->episode->reason,
                'patient' => ['id' => $stay->episode->patient->local_patient_id, 'code' => $stay->episode->patient->code, 'name' => $stay->episode->patient->fullName()],
            ] : null,
        ];
    }
}

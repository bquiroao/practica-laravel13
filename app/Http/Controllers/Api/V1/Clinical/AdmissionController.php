<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\AdmissionRequest;
use App\Http\Resources\AdmissionResource;
use App\Models\Hospital\Bed;
use App\Models\Hospital\CareEpisode;
use App\Services\Admissions\AdmissionService;
use App\Services\Admissions\Eligibility\BedRequirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Módulo admisión hospitalaria y asignación de cama. Cada respuesta lleva X-Request-Id, que también queda en la bitácora. */
class AdmissionController extends Controller
{
    public function __construct(private readonly AdmissionService $admissions) {}

    /** CU-03: camas disponibles y compatibles con lo que necesita el paciente. */
    public function availableBeds(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bed_type' => ['required', Rule::in(array_keys(Bed::TYPES))],
            'isolation' => ['required', 'boolean'],
            'ward_id' => ['nullable', 'uuid'],
        ], [], ['bed_type' => 'tipo de cama', 'isolation' => 'aislamiento', 'ward_id' => 'sala']);
        $beds = $this->admissions->availableBeds(new BedRequirement($data['bed_type'], (bool) $data['isolation'], $data['ward_id'] ?? null));

        return $this->withRequestId($request, response()->json([
            'data' => $beds->map(fn (Bed $b) => [
                'id' => $b->bed_id, 'code' => $b->code, 'type' => $b->type, 'type_label' => Bed::TYPES[$b->type] ?? $b->type,
                'ward' => ['id' => $b->ward->ward_id, 'code' => $b->ward->code, 'name' => $b->ward->name, 'location' => $b->ward->location],
            ])->values(),
            'total' => $beds->count(),
        ]));
    }

    /** CU-01: admitir y asignar la cama, todo o nada. */
    public function store(AdmissionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $episode = $this->admissions->admit($request->user(), $request->attributes->get('hospital'), [
            'patient_id' => $data['patient_id'], 'bed_id' => $data['bed_id'], 'source' => $data['source'], 'reason' => $data['reason'],
            'requirement' => new BedRequirement($data['bed_type'], (bool) $data['isolation'], $data['ward_id'] ?? null),
        ], $request->ip(), $this->requestId($request));

        return $this->withRequestId($request, (new AdmissionResource($episode))->response()->setStatusCode(201)
            ->header('Location', url('/api/v1/admisiones/'.$episode->care_episode_id)));
    }

    /** CU-06: consultar una admisión del propio hospital. */
    public function show(Request $request, string $admission): JsonResponse
    {
        return $this->withRequestId($request, (new AdmissionResource($this->find($admission)))->response());
    }

    /** Trazabilidad de la admisión, en orden (RN-05). */
    public function events(Request $request, string $admission): JsonResponse
    {
        return $this->withRequestId($request, response()->json(['data' => $this->admissions->events($request->attributes->get('hospital'), $this->find($admission))]));
    }

    private function find(string $id): CareEpisode
    {
        $episode = CareEpisode::query()->with(['patient', 'stays.bed.ward'])->where('type', 'inpatient')->find($id);

        return $episode ?? throw new DomainRuleException('ADMISION_NO_ENCONTRADA', 'La admisión no existe en este hospital.', 404);
    }

    /** Se respeta el X-Request-Id del cliente si es un UUID (así lo guarda la bitácora); si no, se genera uno. */
    private function requestId(Request $request): string
    {
        if (! $request->attributes->has('request_id')) {
            $given = (string) $request->header('X-Request-Id');
            $request->attributes->set('request_id', Str::isUuid($given) ? strtolower($given) : (string) Str::uuid());
        }

        return $request->attributes->get('request_id');
    }

    private function withRequestId(Request $request, JsonResponse $response): JsonResponse
    {
        return $response->header('X-Request-Id', $this->requestId($request));
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\PatientRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\PatientResource;
use App\Models\Hospital\Appointment;
use App\Models\Hospital\LocalPatient;
use App\Services\Patients\PatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PatientController extends Controller
{
    public function __construct(private readonly PatientService $patients) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return PatientResource::collection($this->patients->search((string) $request->query('q')))
            ->additional(['meta' => ['criterio' => trim((string) $request->query('q')) === '' ? 'recientes' : 'busqueda', 'limite' => PatientService::SEARCH_LIMIT]]);
    }

    public function store(PatientRequest $request): JsonResponse
    {
        $patient = $this->patients->register($request->user(), $request->attributes->get('hospital'), $request->validated(),
            $request->boolean('confirmar_duplicado'), (string) $request->input('motivo'), $request->ip());

        return (new PatientResource($patient))->response()->setStatusCode(201);
    }

    public function show(string $patient): JsonResponse
    {
        $model = $this->find($patient);
        $upcoming = Appointment::query()->with(['doctor.userRef', 'specialty'])->where('local_patient_id', $model->local_patient_id)
            ->where('ends_at', '>=', now()->subDays(30))->orderBy('starts_at')->limit(20)->get();

        return response()->json([
            'data' => new PatientResource($model),
            'appointments' => AppointmentResource::collection($upcoming),
        ]);
    }

    public function update(PatientRequest $request, string $patient): PatientResource
    {
        return new PatientResource($this->patients->update($request->user(), $request->attributes->get('hospital'), $this->find($patient),
            $request->validated(), (int) $request->input('version'), (string) $request->input('motivo'), $request->ip()));
    }

    private function find(string $id): LocalPatient
    {
        return LocalPatient::query()->find($id) ?? abort(404, 'Paciente no encontrado.');
    }
}

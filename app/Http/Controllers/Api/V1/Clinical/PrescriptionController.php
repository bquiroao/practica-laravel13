<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Http\Controllers\Controller;
use App\Models\Hospital\LocalPatient;
use App\Models\Hospital\Prescription;
use App\Services\Catalogs\MedicationDirectory;
use App\Services\Prescriptions\PrescriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Módulo prescripciones con validación de alergias. */
class PrescriptionController extends Controller
{
    public function __construct(private readonly PrescriptionService $prescriptions) {}

    public function medications(MedicationDirectory $medications): JsonResponse
    {
        return response()->json(['data' => $medications->all()]);
    }

    /** Recetas recientes del hospital (sin paciente) o de un paciente. */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['paciente' => ['nullable', 'uuid']]);
        $list = Prescription::query()->with(['patient', 'prescriber'])
            ->when($request->query('paciente'), fn ($q, $v) => $q->where('local_patient_id', $v))
            ->orderByDesc('issued_at')->limit(100)->get();

        return response()->json(['data' => $list->map(fn (Prescription $p) => $this->present($p))]);
    }

    public function store(Request $request, string $patient): JsonResponse
    {
        $data = $request->validate([
            'medication_code' => ['required', 'string', 'max:20'], 'dose' => ['required', 'string', 'max:60', 'regex:/\S/'],
            'route' => ['nullable', 'string', 'max:30'], 'frequency' => ['required', 'string', 'max:60', 'regex:/\S/'],
            'duration_days' => ['nullable', 'integer', 'between:1,365'], 'instructions' => ['nullable', 'string', 'max:500'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ], [], ['medication_code' => 'medicamento', 'dose' => 'dosis', 'frequency' => 'frecuencia', 'duration_days' => 'duración', 'instructions' => 'indicaciones']);
        $model = LocalPatient::query()->find($patient) ?? abort(404, 'Paciente no encontrado.');
        $p = $this->prescriptions->issue($request->user(), $request->attributes->get('hospital'), $model, $data, $data['override_reason'] ?? null, $request->ip());

        return response()->json(['data' => $this->present($p->load(['patient', 'prescriber']))], 201);
    }

    public function cancel(Request $request, string $prescription): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $model = Prescription::query()->find($prescription) ?? abort(404, 'Receta no encontrada.');
        $p = $this->prescriptions->cancel($request->user(), $request->attributes->get('hospital'), $model, (string) ($data['reason'] ?? ''), $request->ip());

        return response()->json(['data' => $this->present($p->load(['patient', 'prescriber']))]);
    }

    /** @return array<string, mixed> */
    private function present(Prescription $p): array
    {
        return [
            'id' => $p->prescription_id, 'medication_code' => $p->medication_code, 'medication_name' => $p->medication_name, 'dose' => $p->dose, 'route' => $p->route,
            'frequency' => $p->frequency, 'duration_days' => $p->duration_days, 'instructions' => $p->instructions, 'status' => $p->status,
            'allergy_override_reason' => $p->allergy_override_reason, 'cancel_reason' => $p->cancel_reason, 'issued_at' => $p->issued_at->toIso8601String(),
            'prescriber' => $p->prescriber?->display_name,
            'patient' => ['id' => $p->patient->local_patient_id, 'code' => $p->patient->code, 'name' => $p->patient->fullName()],
        ];
    }
}

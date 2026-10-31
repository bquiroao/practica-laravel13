<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\AllergyRequest;
use App\Models\Hospital\Allergy;
use App\Models\Hospital\LocalPatient;
use App\Models\Hospital\LocalUserRef;
use App\Services\Allergies\AlertCalculator;
use App\Services\Allergies\AlertLevel;
use App\Services\Allergies\AllergyService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Módulo alergias clínicas y alerta visual en el expediente. */
class AllergyController extends Controller
{
    public function __construct(private readonly AllergyService $allergies, private readonly AlertCalculator $calculator) {}

    /** CU-03: la alerta que se muestra arriba de la ficha y del expediente. */
    public function show(Request $request, string $patient): JsonResponse
    {
        return response()->json(['data' => $this->alert($request, $this->patient($patient))]);
    }

    /** CU-01: registrar una alergia verificada. Devuelve la alergia y la alerta recalculada. */
    public function store(AllergyRequest $request, string $patient): JsonResponse
    {
        $p = $this->patient($patient);
        $allergy = $this->allergies->register($request->user(), $request->attributes->get('hospital'), $p, $request->validated(), $request->ip());

        return response()->json(['data' => $this->allergy($allergy), 'alert' => $this->alert($request, $p)], 201);
    }

    /** CU-06: inactivar o resolver, con motivo (RN-10). */
    public function updateStatus(Request $request, string $allergy): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:inactive,resolved'],
            'reason' => ['required', 'string', 'min:5', 'max:300', 'regex:/\S/'],
        ], ['reason.min' => 'Escriba el motivo (al menos 5 letras), p. ej. «Prueba de provocación negativa».'], ['status' => 'estado', 'reason' => 'motivo']);
        $model = Allergy::query()->find($allergy) ?? throw new DomainRuleException('ALERGIA_NO_ENCONTRADA', 'La alergia no existe en este hospital.', 404);
        $model = $this->allergies->changeStatus($request->user(), $request->attributes->get('hospital'), $model, $data['status'], $data['reason'], $request->ip());

        return response()->json(['data' => $this->allergy($model), 'alert' => $this->alert($request, $this->patient($model->local_patient_id))]);
    }

    /** CU-05: «He leído la alerta». Se envía la huella que el usuario vio (RN-09). */
    public function acknowledge(Request $request, string $patient): JsonResponse
    {
        $data = $request->validate(['fingerprint' => ['required', 'string', 'regex:/^[0-9a-f]{16}$/']], [], ['fingerprint' => 'huella de la alerta']);
        $p = $this->patient($patient);
        $this->allergies->acknowledge($request->user(), $request->attributes->get('hospital'), $p, $data['fingerprint'], $request->ip());

        return response()->json(['data' => $this->alert($request, $p)], 201);
    }

    private function patient(string $id): LocalPatient
    {
        return LocalPatient::query()->find($id) ?? throw new DomainRuleException('PACIENTE_NO_ENCONTRADO', 'El paciente no existe en este hospital.', 404);
    }

    private function alert(Request $request, LocalPatient $patient): array
    {
        $alert = $this->allergies->alert($request->user(), $request->attributes->get('hospital'), $patient);
        $level = $alert['level'];

        return [
            'patient' => ['id' => $patient->local_patient_id, 'code' => $patient->code],
            'level' => $level->value, 'level_label' => $level->label(),
            'requires_acknowledgement' => $level->requiresAcknowledgement() && ! $alert['acknowledged'],
            'acknowledged' => $alert['acknowledged'], 'fingerprint' => $alert['fingerprint'],
            'allergies' => array_map(fn (array $d) => $this->allergy($d['allergy'], $d['level']), $alert['detail']),
            'history' => array_map(fn (Allergy $a) => $this->allergy($a), $alert['history']),
        ];
    }

    private function allergy(Allergy $a, ?AlertLevel $level = null): array
    {
        $level ??= $this->calculator->levelFor($a);

        return [
            'id' => $a->allergy_id, 'allergen' => AlertCalculator::allergen($a), 'allergen_name' => $a->allergen_name, 'allergen_code' => $a->allergen_code,
            'type' => $a->allergy_type, 'type_label' => AllergyService::TYPES[$a->allergy_type] ?? $a->allergy_type,
            'severity' => $a->severity, 'severity_label' => AllergyService::SEVERITIES[$a->severity] ?? $a->severity,
            'reaction' => $a->reaction, 'onset_date' => $a->onset_date, 'status' => $a->status, 'status_label' => AllergyService::STATUSES[$a->status] ?? $a->status,
            'resolved_at' => $a->resolved_at, 'recorded_at' => $a->recorded_at ? CarbonImmutable::parse($a->recorded_at)->toIso8601String() : null,
            'verified_by' => LocalUserRef::query()->whereKey($a->verified_by_user_ref_id)->value('display_name'),
            'level' => $level->value, 'level_label' => $level->label(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientResource;
use App\Models\Hospital\Appointment;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\ClinicalNote;
use App\Models\Hospital\Diagnosis;
use App\Models\Hospital\LocalPatient;
use App\Models\Hospital\MedicalRecord;
use App\Models\Hospital\VitalSign;
use App\Services\Access\PermissionResolver;
use App\Services\Records\ClinicalRecordService;
use App\Services\Records\NoteService;
use App\Services\Records\VitalSignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Módulos expediente médico, notas SOAP y diagnósticos, y signos vitales. */
class RecordController extends Controller
{
    public function __construct(private readonly ClinicalRecordService $records, private readonly NoteService $notes, private readonly VitalSignService $vitals) {}

    public function show(Request $request, string $patient): JsonResponse
    {
        $model = $this->patient($patient);
        $record = MedicalRecord::query()->where('local_patient_id', $model->local_patient_id)->first();
        $episodes = CareEpisode::query()->where('local_patient_id', $model->local_patient_id)->with('stays.bed.ward')->orderByDesc('started_at')->limit(30)->get();
        $notes = $record ? ClinicalNote::query()->where('medical_record_id', $record->medical_record_id)->with(['author', 'diagnoses'])->orderByDesc('created_at')->orderByDesc('version_no')->get() : collect();
        $superseded = $notes->pluck('supersedes_id')->filter()->all();
        $vitals = $record ? VitalSign::query()->where('medical_record_id', $record->medical_record_id)->where('status', 'validated')->with('recorder')
            ->orderByDesc('measured_at')->limit(30)->get() : collect();
        $episodeIds = $episodes->pluck('care_episode_id');

        return response()->json([
            'patient' => new PatientResource($model),
            'record_number' => $record?->record_number,
            'episodes' => $episodes->map(fn (CareEpisode $e) => [
                'id' => $e->care_episode_id, 'type' => $e->type, 'status' => $e->status, 'reason' => $e->reason,
                'started_at' => $e->started_at->toIso8601String(), 'ended_at' => $e->ended_at?->toIso8601String(), 'discharge_type' => $e->discharge_type,
                'bed' => ($stay = $e->stays->whereNull('ended_at')->first()) ? $stay->bed->ward->name.' · '.$stay->bed->code : null,
            ]),
            'notes' => $notes->map(fn (ClinicalNote $n) => [
                'id' => $n->clinical_note_id, 'episode_id' => $n->care_episode_id, 'status' => $n->status, 'version' => $n->version_no,
                'sections' => $n->sections(), 'author' => $n->author?->display_name, 'author_ref' => $n->author_user_ref_id,
                'created_at' => $n->created_at->toIso8601String(), 'signed_at' => $n->signed_at?->toIso8601String(),
                'correction_reason' => $n->correction_reason, 'superseded' => in_array($n->clinical_note_id, $superseded, true),
                'diagnoses' => $n->diagnoses->map(fn (Diagnosis $d) => ['code' => $d->code, 'description' => $d->description, 'type' => $d->type, 'status' => $d->status])->values(),
            ]),
            'diagnoses' => Diagnosis::query()->whereIn('care_episode_id', $episodeIds)->where('status', 'active')->orderBy('created_at')->get()
                ->map(fn (Diagnosis $d) => ['code' => $d->code, 'description' => $d->description, 'type' => $d->type, 'since' => $d->onset_date->toDateString()]),
            'vitals' => $vitals->map(fn (VitalSign $v) => [
                'id' => $v->vital_sign_id, 'measured_at' => $v->measured_at->toIso8601String(), 'recorded_by' => $v->recorder?->display_name,
                'values' => collect(array_keys(VitalSignService::SIGNS))->mapWithKeys(fn ($k) => [$k => $v->getAttribute($k)]),
                'findings' => VitalSignService::findings($v),
            ]),
            'signs' => collect(VitalSignService::SIGNS)->map(fn ($d, $k) => ['key' => $k, 'label' => $d[0], 'unit' => $d[1], 'min' => $d[2], 'max' => $d[3], 'physical' => [$d[6], $d[7]]])->values(),
        ]);
    }

    public function storeNote(Request $request, string $patient, PermissionResolver $permissions): JsonResponse
    {
        $data = $this->noteData($request);
        $this->requireSignPermission($request, $permissions, (bool) ($data['sign'] ?? false));
        $note = $this->notes->create($request->user(), $request->attributes->get('hospital'), $this->patient($patient), $data['episode_id'] ?? null,
            $data['soap'], $data['diagnoses'] ?? [], (bool) ($data['sign'] ?? false), $request->ip());

        return response()->json(['data' => ['id' => $note->clinical_note_id, 'status' => $note->status]], 201);
    }

    public function updateNote(Request $request, string $note, PermissionResolver $permissions): JsonResponse
    {
        $data = $this->noteData($request);
        $this->requireSignPermission($request, $permissions, (bool) ($data['sign'] ?? false));
        $model = ClinicalNote::query()->find($note) ?? abort(404, 'Nota no encontrada.');
        $updated = $this->notes->updateDraft($request->user(), $request->attributes->get('hospital'), $model, $data['soap'], $data['diagnoses'] ?? [], (bool) ($data['sign'] ?? false), $request->ip());

        return response()->json(['data' => ['id' => $updated->clinical_note_id, 'status' => $updated->status]]);
    }

    public function correctNote(Request $request, string $note): JsonResponse
    {
        $data = $this->noteData($request) + $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $model = ClinicalNote::query()->find($note) ?? abort(404, 'Nota no encontrada.');
        $new = $this->notes->correct($request->user(), $request->attributes->get('hospital'), $model, $data['soap'], $data['diagnoses'] ?? [], (string) ($data['reason'] ?? ''), $request->ip());

        return response()->json(['data' => ['id' => $new->clinical_note_id, 'version' => $new->version_no]], 201);
    }

    public function storeVitals(Request $request, string $patient): JsonResponse
    {
        $data = $request->validate(['episode_id' => ['nullable', 'uuid'], 'measured_at' => ['nullable', 'date'], 'values' => ['required', 'array']]);
        $vital = $this->vitals->record($request->user(), $request->attributes->get('hospital'), $this->patient($patient), $data['episode_id'] ?? null,
            $data['values'], $data['measured_at'] ?? null, $request->ip());

        return response()->json(['data' => ['id' => $vital->vital_sign_id, 'findings' => VitalSignService::findings($vital)]], 201);
    }

    public function startConsultation(Request $request, string $patient): JsonResponse
    {
        $data = $request->validate(['appointment_id' => ['nullable', 'uuid']]);
        $model = $this->patient($patient);
        $appointment = isset($data['appointment_id']) ? Appointment::query()->where('local_patient_id', $model->local_patient_id)->find($data['appointment_id']) : null;
        $episode = $this->records->startConsultation($request->user(), $request->attributes->get('hospital'), $model, $appointment, $request->ip());

        return response()->json(['data' => ['id' => $episode->care_episode_id]], 201);
    }

    public function endConsultation(Request $request, string $episode): JsonResponse
    {
        $model = CareEpisode::query()->find($episode) ?? abort(404, 'Atención no encontrada.');
        $this->records->endConsultation($request->user(), $request->attributes->get('hospital'), $model, $request->ip());

        return response()->json(['data' => ['id' => $model->care_episode_id, 'status' => 'closed']]);
    }

    /** @return array<string, mixed> */
    private function noteData(Request $request): array
    {
        return $request->validate([
            'episode_id' => ['nullable', 'uuid'],
            'soap' => ['required', 'array'],
            'soap.s' => ['nullable', 'string', 'max:5000'], 'soap.o' => ['nullable', 'string', 'max:5000'],
            'soap.a' => ['nullable', 'string', 'max:5000'], 'soap.p' => ['nullable', 'string', 'max:5000'],
            'diagnoses' => ['nullable', 'array', 'max:10'],
            'diagnoses.*.code' => ['nullable', 'string', 'max:10'], 'diagnoses.*.description' => ['nullable', 'string', 'max:300'],
            'diagnoses.*.type' => ['nullable', 'string'],
            'sign' => ['sometimes', 'boolean'],
        ]);
    }

    private function requireSignPermission(Request $request, PermissionResolver $permissions, bool $sign): void
    {
        abort_if($sign && ! $permissions->allows($request->user()->user_id, $request->attributes->get('hospital')->hospital_id, 'notas.firmar'), 403, 'No tiene permiso para firmar notas.');
    }

    private function patient(string $id): LocalPatient
    {
        return LocalPatient::query()->find($id) ?? abort(404, 'Paciente no encontrado.');
    }
}

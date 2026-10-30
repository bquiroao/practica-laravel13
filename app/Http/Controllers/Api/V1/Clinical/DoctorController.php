<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Http\Controllers\Controller;
use App\Models\Central\CentralUser;
use App\Models\Central\HospitalMembership;
use App\Models\Hospital\DoctorProfile;
use App\Models\Hospital\DoctorSpecialty;
use App\Models\Hospital\LocalUserRef;
use App\Services\Doctors\DoctorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Módulo médicos-especialidades. */
class DoctorController extends Controller
{
    public function __construct(private readonly DoctorService $doctors) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['especialidad' => ['nullable', 'uuid'], 'estado' => ['nullable', 'in:active,inactive,suspended']]);
        $doctors = DoctorProfile::query()->with(['userRef', 'specialties' => fn ($q) => $q->validOn(now()->toDateString())->with('specialty'), 'schedules'])
            ->when($request->query('estado'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('especialidad'), fn ($q, $v) => $q->whereHas('specialties', fn ($s) => $s->where('specialty_id', $v)->validOn(now()->toDateString())))
            ->get()->sortBy(fn (DoctorProfile $d) => mb_strtolower($d->name()))->values();

        return response()->json(['data' => $doctors->map(fn (DoctorProfile $d) => $this->present($d))]);
    }

    /** Personas con rol «Médico» en el hospital que todavía no tienen perfil: el primer paso para registrar uno. */
    public function candidates(Request $request): JsonResponse
    {
        $hospitalId = $request->attributes->get('hospital')->hospital_id;
        $withProfile = LocalUserRef::query()->whereIn('local_user_ref_id', DoctorProfile::query()->active()->select('local_user_ref_id'))->pluck('central_user_id');
        $ids = HospitalMembership::query()->current()->where('hospital_id', $hospitalId)->where('role_code', 'medico')->pluck('user_id')->diff($withProfile);

        return response()->json(['data' => CentralUser::query()->whereIn('user_id', $ids)->where('status', 'active')->orderBy('display_name')
            ->get()->map(fn (CentralUser $u) => ['id' => $u->user_id, 'name' => $u->display_name, 'email' => $u->email])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'uuid'],
            'license_number' => ['required', 'string', 'max:30', 'regex:/\S/'],
            'specialty_id' => ['required', 'uuid'],
        ], [], ['license_number' => 'número de colegiado', 'specialty_id' => 'especialidad', 'user_id' => 'persona']);
        $profile = $this->doctors->create($request->user(), $request->attributes->get('hospital'), $data['user_id'], $data['license_number'], $data['specialty_id'], $request->ip());

        return response()->json(['data' => $this->present($this->load($profile->doctor_profile_id))], 201);
    }

    public function show(string $doctor): JsonResponse
    {
        return response()->json(['data' => $this->present($this->load($doctor), true)]);
    }

    public function addSpecialty(Request $request, string $doctor): JsonResponse
    {
        $data = $request->validate(['specialty_id' => ['required', 'uuid'], 'is_primary' => ['sometimes', 'boolean']], [], ['specialty_id' => 'especialidad']);
        $this->doctors->addSpecialty($request->user(), $request->attributes->get('hospital'), $this->load($doctor), $data['specialty_id'], (bool) ($data['is_primary'] ?? false), $request->ip());

        return response()->json(['data' => $this->present($this->load($doctor), true)], 201);
    }

    public function endSpecialty(Request $request, string $doctor, string $link): JsonResponse
    {
        $model = DoctorSpecialty::query()->where('doctor_profile_id', $doctor)->validOn(now()->toDateString())->find($link) ?? abort(404, 'La especialidad no está vigente.');
        $this->doctors->endSpecialty($request->user(), $request->attributes->get('hospital'), $model, $request->ip());

        return response()->json(['data' => $this->present($this->load($doctor), true)]);
    }

    public function schedule(Request $request, string $doctor): JsonResponse
    {
        $data = $request->validate([
            'blocks' => ['present', 'array', 'max:40'],
            'blocks.*.weekday' => ['required', 'integer', 'between:1,7'],
            'blocks.*.starts_at' => ['required', 'string'],
            'blocks.*.ends_at' => ['required', 'string'],
        ]);
        $this->doctors->setSchedule($request->user(), $request->attributes->get('hospital'), $this->load($doctor), $data['blocks'], $request->ip());

        return response()->json(['data' => $this->present($this->load($doctor), true)]);
    }

    public function updateStatus(Request $request, string $doctor): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive', 'suspended'])]], [], ['status' => 'estado']);
        $this->doctors->setStatus($request->user(), $request->attributes->get('hospital'), $this->load($doctor), $data['status'], $request->ip());

        return response()->json(['data' => $this->present($this->load($doctor), true)]);
    }

    private function load(string $id): DoctorProfile
    {
        return DoctorProfile::query()->with(['userRef', 'specialties' => fn ($q) => $q->validOn(now()->toDateString())->with('specialty'), 'schedules'])
            ->find($id) ?? abort(404, 'Médico no encontrado.');
    }

    /** @return array<string, mixed> */
    private function present(DoctorProfile $d, bool $detail = false): array
    {
        return [
            'id' => $d->doctor_profile_id,
            'name' => $d->name(),
            'license_number' => $d->license_number,
            'status' => $d->status,
            'specialties' => $d->specialties->sortByDesc('is_primary')->values()->map(fn (DoctorSpecialty $s) => [
                'link_id' => $s->doctor_specialty_id, 'id' => $s->specialty_id, 'name' => $s->specialty->name, 'is_primary' => $s->is_primary,
                'valid_from' => $s->valid_from->toDateString(),
            ]),
            'schedule' => $d->schedules->map(fn ($b) => ['weekday' => $b->weekday, 'starts_at' => substr($b->starts_at, 0, 5), 'ends_at' => substr($b->ends_at, 0, 5)])->values(),
            'hours_per_week' => round($d->schedules->sum(fn ($b) => (strtotime($b->ends_at) - strtotime($b->starts_at)) / 3600), 1),
        ] + ($detail ? ['valid_from' => $d->valid_from->toDateString()] : []);
    }
}

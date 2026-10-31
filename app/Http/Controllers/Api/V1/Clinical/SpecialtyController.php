<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\Hospital\Specialty;
use App\Services\Audit\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Catálogo de especialidades del hospital (módulo médicos-especialidades). */
class SpecialtyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $all = $request->boolean('todas');
        $counts = DB::connection('hospital')->table('doctor_specialties')->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', now()->toDateString()))
            ->groupBy('specialty_id')->selectRaw('specialty_id, count(*) AS total')->pluck('total', 'specialty_id');

        return response()->json(['data' => Specialty::query()->when(! $all, fn ($q) => $q->active())->orderBy('name')->get()
            ->map(fn (Specialty $s) => ['id' => $s->specialty_id, 'code' => $s->code, 'name' => $s->name, 'status' => $s->status,
                'doctors' => (int) ($counts[$s->specialty_id] ?? 0)])]);
    }

    public function store(Request $request, AuditTrail $audit): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'regex:/\p{L}/u']], [], ['name' => 'nombre']);
        $name = trim((string) preg_replace('/\s+/', ' ', $data['name']));
        $code = Str::upper(Str::limit(Str::slug(Str::ascii($name), '_'), 30, ''));
        if (Specialty::query()->where('code', $code)->exists()) {
            throw new DomainRuleException('ESPECIALIDAD_EXISTENTE', 'Ya existe una especialidad con ese nombre.', 409);
        }
        $specialty = Specialty::query()->create(['code' => $code, 'name' => $name, 'status' => 'active']);
        $audit->record('medicos', 'ESPECIALIDAD_CREADA', 'exito', $request->attributes->get('hospital')->hospital_id, $request->user()->user_id, 'especialidad:'.$code, [], $request->ip());

        return response()->json(['data' => ['id' => $specialty->specialty_id, 'code' => $code, 'name' => $name, 'status' => 'active', 'doctors' => 0]], 201);
    }

    public function update(Request $request, string $specialty, AuditTrail $audit): JsonResponse
    {
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:80', 'regex:/\p{L}/u'], 'status' => ['sometimes', 'in:active,inactive']], [], ['name' => 'nombre', 'status' => 'estado']);
        $model = Specialty::query()->find($specialty) ?? abort(404, 'Especialidad no encontrada.');
        $model->fill(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data))->save();
        $audit->record('medicos', 'ESPECIALIDAD_EDITADA', 'exito', $request->attributes->get('hospital')->hospital_id, $request->user()->user_id, 'especialidad:'.$model->code, $data, $request->ip());

        return response()->json(['data' => ['id' => $model->specialty_id, 'code' => $model->code, 'name' => $model->name, 'status' => $model->status]]);
    }
}

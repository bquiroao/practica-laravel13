<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdmissionResource;
use App\Models\Hospital\AdmissionOrder;
use App\Models\Hospital\Bed;
use App\Services\Admissions\AdmissionOrderService;
use App\Services\Admissions\AdmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Mejora del módulo admisión: órdenes de ingreso pendientes de cama. */
class AdmissionOrderController extends Controller
{
    public function __construct(private readonly AdmissionOrderService $orders) {}

    /** `estado`: pendiente (la lista de espera, la más antigua primero, urgentes arriba) o todas (las últimas 100). */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['estado' => ['nullable', 'in:pendiente,todas']], [], ['estado' => 'estado']);
        $pending = ($data['estado'] ?? 'pendiente') === 'pendiente';
        $list = AdmissionOrder::query()->with(['patient', 'orderedBy', 'assignedBy', 'cancelledBy'])
            ->when($pending, fn ($q) => $q->where('status', 'pendiente')->orderByRaw("priority = 'urgente' DESC")->orderBy('ordered_at'),
                fn ($q) => $q->orderByDesc('ordered_at')->limit(100))
            ->get();

        return response()->json(['data' => $list->map(fn (AdmissionOrder $o) => $this->present($o))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'uuid'],
            'bed_type' => ['required', Rule::in(array_keys(Bed::TYPES))],
            'isolation' => ['required', 'boolean'],
            'source' => ['required', Rule::in(array_keys(AdmissionService::SOURCES))],
            'priority' => ['required', Rule::in(array_keys(AdmissionOrderService::PRIORITIES))],
            'reason' => ['required', 'string', 'min:5', 'max:500', 'regex:/\S/'],
        ], ['reason.min' => 'Escriba el motivo del ingreso (al menos 5 letras), p. ej. «Neumonía, requiere oxígeno».'],
            ['patient_id' => 'paciente', 'bed_type' => 'tipo de cama', 'isolation' => 'aislamiento', 'source' => 'origen', 'priority' => 'prioridad', 'reason' => 'motivo del ingreso']);
        $order = $this->orders->order($request->user(), $request->attributes->get('hospital'), $data, $request->ip());

        return response()->json(['data' => $this->present($order->load(['patient', 'orderedBy']))], 201);
    }

    /** Asignar la cama: crea el internamiento como una admisión directa y cumple la orden. */
    public function assign(Request $request, string $order): JsonResponse
    {
        $data = $request->validate(['bed_id' => ['required', 'uuid'], 'ward_id' => ['nullable', 'uuid']], [], ['bed_id' => 'cama', 'ward_id' => 'sala']);
        $given = (string) $request->header('X-Request-Id');
        $requestId = Str::isUuid($given) ? strtolower($given) : (string) Str::uuid();
        $episode = $this->orders->assign($request->user(), $request->attributes->get('hospital'), $this->find($order), $data['bed_id'], $data['ward_id'] ?? null,
            $request->ip(), $requestId);

        return (new AdmissionResource($episode))->response()->setStatusCode(201)->header('X-Request-Id', $requestId)
            ->header('Location', url('/api/v1/admisiones/'.$episode->care_episode_id));
    }

    public function cancel(Request $request, string $order): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300', 'regex:/\S/']],
            ['reason.min' => 'Escriba por qué se cancela (al menos 5 letras), p. ej. «Mejoró y se fue a casa».'], ['reason' => 'motivo']);
        $o = $this->orders->cancel($request->user(), $request->attributes->get('hospital'), $this->find($order), $data['reason'], $request->ip());

        return response()->json(['data' => $this->present($o->load(['patient', 'orderedBy', 'cancelledBy']))]);
    }

    private function find(string $id): AdmissionOrder
    {
        return AdmissionOrder::query()->find($id) ?? throw new DomainRuleException('ORDEN_NO_ENCONTRADA', 'La orden de ingreso no existe en este hospital.', 404);
    }

    /** @return array<string, mixed> */
    private function present(AdmissionOrder $o): array
    {
        $end = $o->assigned_at ?? $o->cancelled_at ?? now();

        return [
            'id' => $o->admission_order_id, 'status' => $o->status, 'status_label' => AdmissionOrderService::STATUSES[$o->status],
            'patient' => ['id' => $o->local_patient_id, 'code' => $o->patient->code, 'name' => $o->patient->fullName()],
            'bed_type' => $o->bed_type, 'bed_type_label' => Bed::TYPES[$o->bed_type], 'isolation' => $o->isolation,
            'source' => $o->source, 'source_label' => AdmissionService::SOURCES[$o->source],
            'priority' => $o->priority, 'priority_label' => AdmissionOrderService::PRIORITIES[$o->priority], 'reason' => $o->reason,
            'ordered_at' => $o->ordered_at->toIso8601String(), 'ordered_by' => $o->orderedBy?->display_name,
            'waiting_minutes' => (int) $o->ordered_at->diffInMinutes($end),
            'admission_id' => $o->care_episode_id, 'assigned_at' => $o->assigned_at?->toIso8601String(), 'assigned_by' => $o->assignedBy?->display_name,
            'cancelled_at' => $o->cancelled_at?->toIso8601String(), 'cancelled_by' => $o->cancelledBy?->display_name, 'cancel_reason' => $o->cancel_reason,
        ];
    }
}

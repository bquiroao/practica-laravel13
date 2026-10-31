<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\Hospital\CriticalAlert;
use App\Models\Hospital\CriticalAlertEvent;
use App\Models\Hospital\CriticalAlertNotification;
use App\Models\Hospital\CriticalAlertPolicy;
use App\Models\Hospital\LocalUserRef;
use App\Services\Access\PermissionResolver;
use App\Services\Alerts\AlertHistory;
use App\Services\Alerts\CriticalAlertService;
use App\Services\Alerts\EscalationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Módulo alertas críticas y notificaciones internas. Cada hospital tiene su propia base, así que una alerta de otro
 * hospital simplemente no existe aquí (RNF-01).
 */
class CriticalAlertController extends Controller
{
    public function __construct(private readonly CriticalAlertService $alerts, private readonly PermissionResolver $permissions) {}

    /** La campana: cuántas alertas esperan el acuse de este usuario. */
    public function summary(Request $request): JsonResponse
    {
        $me = $this->me($request);

        return response()->json(['data' => [
            'pendientes' => $me ? $this->notifiedTo($me)->whereIn('status', ['ENVIADA', 'ESCALADA'])->count() : 0,
            'abiertas' => CriticalAlert::query()->whereIn('status', ['CREADA', 'ENVIADA', 'ESCALADA'])->count(),
        ]]);
    }

    /** `vista`: mias (esperan mi acuse), abiertas (sin acuse, de todo el hospital) o recientes (las últimas 100). */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['vista' => ['nullable', 'in:mias,abiertas,recientes']], [], ['vista' => 'vista']);
        $me = $this->me($request);
        $query = match ($data['vista'] ?? 'mias') {
            'mias' => $me ? $this->notifiedTo($me)->whereIn('status', ['ENVIADA', 'ESCALADA']) : CriticalAlert::query()->whereRaw('false'),
            'abiertas' => CriticalAlert::query()->whereIn('status', ['CREADA', 'ENVIADA', 'ESCALADA']),
            'recientes' => CriticalAlert::query(),
        };
        $list = $query->with(['acknowledger', 'closer'])->orderByRaw("priority = 'CRITICA' DESC")->orderByDesc('created_at')->limit(100)->get();

        return response()->json(['data' => $list->map(fn (CriticalAlert $a) => $this->present($request, $a, $me))]);
    }

    /** CU-07: la alerta con su historial completo, en orden, y a quién se notificó. */
    public function show(Request $request, string $alert): JsonResponse
    {
        $a = $this->find($alert)->load(['events', 'notifications.recipient', 'acknowledger', 'closer']);

        return response()->json(['data' => $this->present($request, $a, $this->me($request)) + [
            'events' => $a->events->map(fn (CriticalAlertEvent $e) => ['id' => $e->critical_alert_event_id, 'event' => $e->event,
                'event_label' => AlertHistory::EVENTS[$e->event], 'level' => $e->level, 'actor' => $e->actor, 'detail' => $e->detail,
                'occurred_at' => $e->occurred_at->toIso8601String()]),
            'notifications' => $a->notifications->sortBy('level')->values()->map(fn (CriticalAlertNotification $n) => ['recipient' => $n->recipient?->display_name,
                'level' => $n->level, 'attempts' => $n->attempts, 'delivered' => $n->delivered, 'read_at' => $n->read_at?->toIso8601String()]),
        ]]);
    }

    /** CU-04: acusar. */
    public function acknowledge(Request $request, string $alert): JsonResponse
    {
        $a = $this->alerts->acknowledge($request->user(), $request->attributes->get('hospital'), $this->find($alert), $request->ip());

        return response()->json(['data' => $this->present($request, $a->load(['acknowledger', 'closer']), $this->me($request))]);
    }

    /** CU-06: cerrar con nota. */
    public function close(Request $request, string $alert): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500', 'regex:/\p{L}/u']],
            ['note.min' => 'Escriba qué se hizo (al menos 5 letras), p. ej. «Se repitió la muestra y se ajustó el tratamiento».', 'note.required' => 'La nota es obligatoria para cerrar.'],
            ['note' => 'nota']);
        $a = $this->alerts->close($request->user(), $request->attributes->get('hospital'), $this->find($alert), $data['note'], $request->ip());

        return response()->json(['data' => $this->present($request, $a->load(['acknowledger', 'closer']), $this->me($request))]);
    }

    /** CU-08: plazos de acuse por prioridad. */
    public function policies(): JsonResponse
    {
        return response()->json(['data' => $this->policyList()]);
    }

    public function updatePolicies(Request $request): JsonResponse
    {
        $range = ['required', 'integer', 'between:'.EscalationPolicy::MIN_MINUTES.','.EscalationPolicy::MAX_MINUTES];
        $data = $request->validate(['CRITICA' => $range, 'ALTA' => $range],
            ['between' => 'El plazo de :attribute va de 5 a 60 minutos.'], ['CRITICA' => 'la prioridad crítica', 'ALTA' => 'la prioridad alta']);
        $this->alerts->updatePolicies($request->user(), $request->attributes->get('hospital'), $data, $request->ip());

        return response()->json(['data' => $this->policyList()]);
    }

    /** @return list<array<string, mixed>> */
    private function policyList(): array
    {
        $saved = CriticalAlertPolicy::query()->pluck('ack_minutes', 'priority');

        return collect(CriticalAlertService::PRIORITIES)->map(fn ($label, $p) => ['priority' => $p, 'label' => $label,
            'ack_minutes' => $saved[$p] ?? EscalationPolicy::DEFAULT_MINUTES[$p]])->values()->all();
    }

    private function find(string $id): CriticalAlert
    {
        return CriticalAlert::query()->find($id) ?? throw new DomainRuleException('ALERTA_NO_ENCONTRADA', 'La alerta no existe en este hospital.', 404);
    }

    /** La referencia local del usuario, sin crearla: quien nunca recibió una alerta no tiene nada que acusar. */
    private function me(Request $request): ?LocalUserRef
    {
        return LocalUserRef::query()->where('central_user_id', $request->user()->user_id)->first();
    }

    private function notifiedTo(LocalUserRef $me): Builder
    {
        return CriticalAlert::query()->whereHas('notifications', fn ($q) => $q->where('recipient_user_ref_id', $me->local_user_ref_id)->where('delivered', true));
    }

    /** @return array<string, mixed> */
    private function present(Request $request, CriticalAlert $a, ?LocalUserRef $me): array
    {
        $mine = $me ? $a->notifications()->where('recipient_user_ref_id', $me->local_user_ref_id)->where('delivered', true)->first() : null;
        $canClose = $this->permissions->allows($request->user()->user_id, $request->attributes->get('hospital')->hospital_id, 'alertas.cerrar');

        return [
            'id' => $a->critical_alert_id, 'patient' => ['id' => $a->local_patient_id, 'code' => $a->patient_code],
            'source' => $a->source, 'source_label' => CriticalAlertService::SOURCES[$a->source], 'priority' => $a->priority,
            'priority_label' => CriticalAlertService::PRIORITIES[$a->priority], 'title' => $a->title, 'description' => $a->description,
            'status' => $a->status, 'status_label' => CriticalAlertService::STATUSES[$a->status], 'level' => $a->level,
            'level_label' => EscalationPolicy::LEVELS[$a->level], 'deadline_at' => $a->deadline_at?->toIso8601String(),
            'escalation_exhausted' => $a->escalation_exhausted,
            'acknowledged_by' => $a->acknowledger?->display_name, 'acknowledged_at' => $a->acknowledged_at?->toIso8601String(),
            'closed_by' => $a->closer?->display_name, 'closed_at' => $a->closed_at?->toIso8601String(), 'close_note' => $a->close_note,
            'created_at' => $a->created_at->toIso8601String(),
            'notified_to_me' => $mine !== null, 'can_acknowledge' => $mine !== null && $a->awaitingAcknowledgement(),
            'can_close' => $canClose && $a->status === 'ACUSADA',
        ];
    }
}

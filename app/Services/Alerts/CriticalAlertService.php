<?php

namespace App\Services\Alerts;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\CriticalAlert;
use App\Models\Hospital\CriticalAlertPolicy;
use App\Services\Audit\AuditTrail;
use App\Services\Hospital\UserRefSync;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Módulo alertas críticas y notificaciones internas: recibe el evento crítico de otro módulo, avisa al personal del
 * nivel que corresponde, registra el acuse y, si nadie acusa en el plazo, escala al nivel siguiente. Es el único que
 * escribe en las tablas critical_alert*. Cada responsabilidad vive en su clase (SRP, semana 2): destinatarios
 * (RecipientResolver), envío y reintentos (NotificationDispatcher), plazos (EscalationPolicy) e historial (AlertHistory).
 */
class CriticalAlertService
{
    public const PRIORITIES = ['CRITICA' => 'Crítica', 'ALTA' => 'Alta'];

    public const SOURCES = ['laboratorio' => 'Laboratorio', 'signos_vitales' => 'Signos vitales'];

    public const STATUSES = ['CREADA' => 'Creada', 'ENVIADA' => 'Enviada', 'ESCALADA' => 'Escalada', 'ACUSADA' => 'Acusada', 'CERRADA' => 'Cerrada'];

    public function __construct(
        private readonly RecipientResolver $recipients,
        private readonly NotificationDispatcher $dispatcher,
        private readonly EscalationPolicy $policy,
        private readonly AlertHistory $history,
        private readonly UserRefSync $refs,
        private readonly AuditTrail $audit,
    ) {}

    /**
     * CU-01: crea la alerta y la envía al nivel 0. RN-01: exige paciente, origen, id de origen, prioridad y descripción.
     * RN-02: si el mismo origen + id de origen ya llegó, devuelve la alerta existente sin crear otra.
     *
     * @param  array{patient_id?: string, patient_code?: string, care_episode_id?: string|null, source?: string, source_id?: string, priority?: string, title?: string, description?: string}  $data
     */
    public function register(Hospital $hospital, array $data): CriticalAlert
    {
        foreach (['patient_id', 'patient_code', 'source', 'source_id', 'priority', 'title', 'description'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw new DomainRuleException('ALERTA_INVALIDA', "Falta «{$field}»: la alerta no se registró.", 422, ['campo' => $field]);
            }
        }
        if (! isset(self::SOURCES[$data['source']]) || ! isset(self::PRIORITIES[$data['priority']])) {
            throw new DomainRuleException('ALERTA_INVALIDA', 'El origen o la prioridad no son válidos: la alerta no se registró.', 422);
        }
        if ($existing = $this->bySource($data['source'], $data['source_id'])) {
            return $existing;
        }

        try {
            $alert = DB::connection('hospital')->transaction(function () use ($hospital, $data) {
                $alert = CriticalAlert::query()->create([
                    'local_patient_id' => $data['patient_id'], 'patient_code' => $data['patient_code'], 'care_episode_id' => $data['care_episode_id'] ?? null,
                    'source' => $data['source'], 'source_id' => $data['source_id'], 'priority' => $data['priority'],
                    'title' => mb_substr(trim($data['title']), 0, 150), 'description' => mb_substr(trim($data['description']), 0, 500), 'status' => 'CREADA', 'level' => 0,
                ]);
                $this->history->record($alert, 'creada', 0, 'sistema:'.$data['source'], null, ['prioridad' => $data['priority']]);
                $this->route($alert, 0, $hospital);

                return $alert;
            });
        } catch (QueryException $e) {
            // RN-02 en la base: si el mismo evento llegó dos veces a la vez, gana el primero.
            if (str_contains($e->getMessage(), 'critical_alerts_source_unique')) {
                return $this->bySource($data['source'], $data['source_id']);
            }
            throw $e;
        }

        $this->audit->record('alertas', 'ALERTA_CREADA', 'exito', $hospital->hospital_id, null, 'paciente:'.$alert->patient_code,
            ['alerta' => $alert->critical_alert_id, 'origen' => $alert->source, 'prioridad' => $alert->priority, 'nivel' => $alert->level]);

        return $alert->refresh();
    }

    /**
     * CU-05 (lo corre el planificador cada minuto): sube un nivel cada alerta sin acuse cuyo plazo venció (RN-05).
     * En el nivel 2 registra «escalamiento agotado» una sola vez. Devuelve cuántas alertas cambiaron.
     */
    public function escalateDue(Hospital $hospital): int
    {
        $ids = CriticalAlert::query()->whereIn('status', ['ENVIADA', 'ESCALADA'])->where('escalation_exhausted', false)
            ->where('deadline_at', '<=', now())->orderBy('deadline_at')->pluck('critical_alert_id');
        $changed = 0;
        foreach ($ids as $id) {
            $changed += DB::connection('hospital')->transaction(function () use ($id, $hospital): int {
                $alert = CriticalAlert::query()->lockForUpdate()->find($id);
                // Otra persona la acusó mientras corría la revisión (RN-06): ya no se escala.
                if (! $alert || ! $alert->awaitingAcknowledgement() || $alert->escalation_exhausted || $alert->deadline_at > now()) {
                    return 0;
                }
                if ($alert->level >= EscalationPolicy::MAX_LEVEL) {
                    $this->exhaust($alert, 'plazo_vencido');

                    return 1;
                }
                $this->history->record($alert, 'escalada', $alert->level + 1, AlertHistory::SYSTEM_SCHEDULER, null, ['motivo' => 'plazo_vencido', 'desde_nivel' => $alert->level]);
                $this->route($alert, $alert->level + 1, $hospital);

                return 1;
            });
        }

        return $changed;
    }

    /**
     * CU-04: acusa quien recibió la alerta (RN-07). Es idempotente: si ya estaba acusada, no agrega nada (RN-06).
     */
    public function acknowledge(CentralUser $actor, Hospital $hospital, CriticalAlert $alert, ?string $ip): CriticalAlert
    {
        $ref = $this->refs->forUser($actor, $hospital);
        $notified = $alert->notifications()->where('recipient_user_ref_id', $ref->local_user_ref_id)->where('delivered', true)->exists();
        if (! $notified) {
            throw new DomainRuleException('ALERTA_NO_NOTIFICADA', 'Solo puede acusar esta alerta alguien a quien se le notificó.', 403);
        }

        $acknowledged = DB::connection('hospital')->transaction(function () use ($alert, $ref): bool {
            $locked = CriticalAlert::query()->lockForUpdate()->findOrFail($alert->critical_alert_id);
            if (! $locked->awaitingAcknowledgement()) {
                return false;
            }
            $locked->forceFill(['status' => 'ACUSADA', 'acknowledged_by_user_ref_id' => $ref->local_user_ref_id, 'acknowledged_at' => now(),
                'acknowledged_level' => $locked->level, 'deadline_at' => null])->save();
            $this->history->record($locked, 'acusada', $locked->level, '', $ref);

            return true;
        });

        if ($acknowledged) {
            $alert->refresh();
            $this->audit->record('alertas', 'ALERTA_ACUSADA', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$alert->patient_code,
                ['alerta' => $alert->critical_alert_id, 'nivel' => $alert->level, 'minutos' => (int) $alert->created_at->diffInMinutes($alert->acknowledged_at)], $ip);
        }
        $alert->notifications()->where('recipient_user_ref_id', $ref->local_user_ref_id)->whereNull('read_at')->update(['read_at' => now()]);

        return $alert->refresh();
    }

    /** CU-06 y RN-09: solo se cierra una alerta acusada y con nota. El permiso alertas.cerrar lo valida la ruta (D-03). */
    public function close(CentralUser $actor, Hospital $hospital, CriticalAlert $alert, string $note, ?string $ip): CriticalAlert
    {
        $ref = $this->refs->forUser($actor, $hospital);
        DB::connection('hospital')->transaction(function () use ($alert, $ref, $note): void {
            $locked = CriticalAlert::query()->lockForUpdate()->findOrFail($alert->critical_alert_id);
            if ($locked->status === 'CERRADA') {
                throw new DomainRuleException('ALERTA_CERRADA', 'La alerta ya está cerrada.', 409);
            }
            if ($locked->status !== 'ACUSADA') {
                throw new DomainRuleException('ALERTA_NO_ACUSADA', 'Primero alguien a quien se notificó debe acusar la alerta; después se cierra.', 409);
            }
            $locked->forceFill(['status' => 'CERRADA', 'closed_by_user_ref_id' => $ref->local_user_ref_id, 'closed_at' => now(), 'close_note' => trim($note)])->save();
            $this->history->record($locked, 'cerrada', $locked->level, '', $ref, ['nota' => trim($note)]);
        });
        $alert->refresh();
        $this->audit->record('alertas', 'ALERTA_CERRADA', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$alert->patient_code,
            ['alerta' => $alert->critical_alert_id], $ip);

        return $alert;
    }

    /**
     * CU-08: plazos de acuse por prioridad, de 5 a 60 minutos (RN-11, lo valida el controlador). Aplican a los
     * plazos que empiecen desde ahora; los que ya corren no cambian.
     *
     * @param  array<string, int>  $minutes
     */
    public function updatePolicies(CentralUser $actor, Hospital $hospital, array $minutes, ?string $ip): void
    {
        $ref = $this->refs->forUser($actor, $hospital);
        $before = CriticalAlertPolicy::query()->pluck('ack_minutes', 'priority')->all();
        foreach ($minutes as $priority => $value) {
            CriticalAlertPolicy::query()->updateOrCreate(['priority' => $priority], ['ack_minutes' => $value, 'updated_by_user_ref_id' => $ref->local_user_ref_id, 'updated_at' => now()]);
        }
        $this->audit->record('alertas', 'PLAZOS_ACTUALIZADOS', 'exito', $hospital->hospital_id, $actor->user_id, 'politica:alertas',
            ['antes' => $before, 'despues' => $minutes], $ip);
    }

    /**
     * Envía la alerta al nivel indicado. RN-03: si el nivel no tiene a quién avisar, pasa al siguiente. RN-08: si un
     * destinatario agota sus 3 intentos, se escala de inmediato. En el nivel 2 sin salida, «escalamiento agotado».
     */
    private function route(CriticalAlert $alert, int $level, Hospital $hospital): void
    {
        while (true) {
            $alert->forceFill(['level' => $level, 'status' => $level === 0 && $alert->status === 'CREADA' ? 'CREADA' : 'ESCALADA'])->save();
            $recipients = $this->recipients->forLevel($alert, $level, $hospital);
            if ($recipients->isEmpty()) {
                $this->history->record($alert, 'sin_destinatarios', $level, AlertHistory::SYSTEM_DISPATCHER, null, ['nivel' => EscalationPolicy::LEVELS[$level]]);
                if ($level < EscalationPolicy::MAX_LEVEL) {
                    $level++;
                    $this->history->record($alert, 'escalada', $level, AlertHistory::SYSTEM_DISPATCHER, null, ['motivo' => 'sin_destinatarios', 'desde_nivel' => $level - 1]);

                    continue;
                }
                $this->exhaust($alert, 'sin_destinatarios');

                return;
            }

            $result = $this->dispatcher->send($alert, $recipients, $level);
            $alert->forceFill(['status' => $level === 0 ? 'ENVIADA' : 'ESCALADA', 'deadline_at' => $this->policy->deadline($alert->priority)])->save();
            if ($result['failed'] !== [] && $level < EscalationPolicy::MAX_LEVEL) {
                $level++;
                $this->history->record($alert, 'escalada', $level, AlertHistory::SYSTEM_DISPATCHER, null, ['motivo' => 'entrega_fallida', 'desde_nivel' => $level - 1]);

                continue;
            }
            if ($result['delivered'] === 0) {
                $this->exhaust($alert, 'entrega_fallida');
            }

            return;
        }
    }

    private function exhaust(CriticalAlert $alert, string $reason): void
    {
        $alert->forceFill(['escalation_exhausted' => true, 'deadline_at' => null, 'status' => $alert->status === 'CREADA' ? 'ESCALADA' : $alert->status])->save();
        $this->history->record($alert, 'escalamiento_agotado', $alert->level, AlertHistory::SYSTEM_SCHEDULER, null, ['motivo' => $reason]);
    }

    private function bySource(string $source, string $sourceId): ?CriticalAlert
    {
        return CriticalAlert::query()->where('source', $source)->where('source_id', $sourceId)->first();
    }
}

<?php

namespace App\Services\Alerts;

use App\Models\Hospital\CriticalAlert;
use App\Models\Hospital\CriticalAlertEvent;
use App\Models\Hospital\LocalUserRef;

/** RN-10: cada transición deja evento, nivel, actor y momento. El historial solo crece (la base lo impide si no). */
class AlertHistory
{
    public const SYSTEM_DISPATCHER = 'sistema:notificaciones';

    public const SYSTEM_SCHEDULER = 'sistema:planificador';

    public const EVENTS = [
        'creada' => 'Creada', 'enviada' => 'Enviada', 'sin_destinatarios' => 'Sin destinatarios en el nivel', 'entrega_fallida' => 'Entrega fallida',
        'escalada' => 'Escalada', 'escalamiento_agotado' => 'Escalamiento agotado', 'acusada' => 'Acusada', 'cerrada' => 'Cerrada',
    ];

    /** @param array<string, mixed> $detail */
    public function record(CriticalAlert $alert, string $event, int $level, string $actor, ?LocalUserRef $user = null, array $detail = []): CriticalAlertEvent
    {
        return CriticalAlertEvent::query()->create([
            'critical_alert_id' => $alert->critical_alert_id, 'event' => $event, 'level' => $level, 'actor' => $user?->display_name ?? $actor,
            'actor_user_ref_id' => $user?->local_user_ref_id, 'detail' => $detail, 'occurred_at' => now(),
        ]);
    }
}

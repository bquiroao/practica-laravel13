<?php

namespace App\Services\Alerts;

use App\Models\Hospital\CriticalAlert;
use App\Models\Hospital\CriticalAlertNotification;
use App\Models\Hospital\LocalUserRef;
use App\Services\Alerts\Channels\AlertChannel;
use Illuminate\Support\Collection;

/**
 * CU-03 y CU-09: envía la alerta a cada destinatario por el puerto de notificación y reintenta una entrega fallida
 * hasta 3 veces (RN-08). Un canal que falla no pierde la alerta: queda guardada y se escala (RNF-05).
 */
class NotificationDispatcher
{
    public function __construct(private readonly AlertChannel $channel, private readonly AlertHistory $history) {}

    /**
     * @param  Collection<int, LocalUserRef>  $recipients
     * @return array{delivered: int, failed: list<string>}
     */
    public function send(CriticalAlert $alert, Collection $recipients, int $level): array
    {
        $delivered = 0;
        $failed = [];
        foreach ($recipients as $recipient) {
            $attempts = 0;
            do {
                $attempts++;
                $ok = $this->deliverSafely($alert, $recipient);
            } while (! $ok && $attempts < EscalationPolicy::MAX_ATTEMPTS);

            CriticalAlertNotification::query()->create(['critical_alert_id' => $alert->critical_alert_id, 'recipient_user_ref_id' => $recipient->local_user_ref_id,
                'level' => $level, 'attempts' => $attempts, 'delivered' => $ok, 'last_attempt_at' => now()]);
            if ($ok) {
                $delivered++;
            } else {
                $failed[] = $recipient->display_name;
                $this->history->record($alert, 'entrega_fallida', $level, AlertHistory::SYSTEM_DISPATCHER, null, ['destinatario' => $recipient->display_name, 'intentos' => $attempts]);
            }
        }
        if ($delivered > 0) {
            $this->history->record($alert, 'enviada', $level, AlertHistory::SYSTEM_DISPATCHER, null, ['destinatarios' => $delivered]);
        }

        return ['delivered' => $delivered, 'failed' => $failed];
    }

    private function deliverSafely(CriticalAlert $alert, LocalUserRef $recipient): bool
    {
        try {
            return $this->channel->deliver($alert, $recipient);
        } catch (\Throwable) {
            return false;
        }
    }
}

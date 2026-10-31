<?php

namespace App\Services\Alerts\Channels;

use App\Models\Hospital\CriticalAlert;
use App\Models\Hospital\LocalUserRef;
use Illuminate\Support\Facades\Log;

/**
 * Canal interno: la notificación es la bandeja de alertas del sistema (campana y página /alertas). No envía correos,
 * SMS ni push. Deja constancia en el registro con la misma hora del sistema que el historial (corrige D-05) y solo con
 * el código del paciente, la prueba o el signo y la prioridad (RNF-02).
 */
class InternalInboxChannel implements AlertChannel
{
    public function deliver(CriticalAlert $alert, LocalUserRef $recipient): bool
    {
        Log::info('alertas.notificacion', ['alerta' => $alert->critical_alert_id, 'destinatario' => $recipient->local_user_ref_id, 'nivel' => $alert->level,
            'paciente' => $alert->patient_code, 'prioridad' => $alert->priority, 'momento' => now()->toIso8601String()]);

        return true;
    }
}

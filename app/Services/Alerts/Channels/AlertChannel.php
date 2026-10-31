<?php

namespace App\Services\Alerts\Channels;

use App\Models\Hospital\CriticalAlert;
use App\Models\Hospital\LocalUserRef;

/**
 * Puerto de notificación (RNF-06): cambiar el canal de envío es escribir otro adaptador y registrarlo en
 * AppServiceProvider, sin tocar los casos de uso. Devuelve false si la entrega falló; quien llama reintenta (RN-08).
 */
interface AlertChannel
{
    public function deliver(CriticalAlert $alert, LocalUserRef $recipient): bool;
}

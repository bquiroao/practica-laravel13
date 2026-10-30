<?php
declare(strict_types=1);

namespace AlertasCriticas\Domain;

/** Puerto de envío. En el curso solo existe un adaptador simulado: no hay correo, SMS ni push reales. */
interface PuertoNotificacion
{
    /** Devuelve true si el mensaje se entregó. Puede lanzar excepción; se cuenta como intento fallido. */
    public function enviar(string $usuario, string $mensaje): bool;
}

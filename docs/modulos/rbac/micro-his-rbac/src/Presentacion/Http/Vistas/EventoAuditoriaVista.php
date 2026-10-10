<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Vistas;

use Rbac\Presentacion\Http\Respuesta;

/** Vista de un evento de auditoría (CU-06). */
final class EventoAuditoriaVista
{
    /** @param array<string, mixed>|null $evento */
    public static function evento(?array $evento): Respuesta
    {
        return $evento === null
            ? ErrorVista::recursoNoEncontrado('Evento no encontrado.')
            : Respuesta::json(200, $evento);
    }
}

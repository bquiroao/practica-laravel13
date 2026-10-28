<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http;

use Auditoria\Presentacion\Http\Controladores\AuditoriaControlador;

/** La tabla de rutas: método + patrón → acción del controlador. */
final class Rutas
{
    public const BASE = '/api/v1/auditoria';

    /** @return list<Ruta> */
    public static function definir(AuditoriaControlador $c): array
    {
        $base = self::BASE;

        return [
            new Ruta('POST', "#^$base/movimientos$#", $c->registrar(...)),
            new Ruta('GET', "#^$base/movimientos$#", $c->consultar(...)),
            new Ruta('GET', "#^$base/movimientos/(?<id>[1-9][0-9]{0,17})$#", $c->verDetalle(...)),
            new Ruta('POST', "#^$base/verificaciones$#", $c->verificar(...)),
            new Ruta('GET', "#^$base/anclas$#", $c->ancla(...)),
        ];
    }
}

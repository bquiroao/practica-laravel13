<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http;

use Prescripciones\Presentacion\Http\Controladores\PrescripcionesControlador;

/** Las rutas de la API en un solo lugar: las usa el kernel y las compara con el contrato la prueba API-01. */
final class Rutas
{
    public const BASE = '/api/v1/prescripciones';

    /** @return list<Ruta> */
    public static function definir(PrescripcionesControlador $c): array
    {
        $base = self::BASE;

        return [
            new Ruta('POST', "#^$base/verificaciones$#", $c->verificar(...)),
            new Ruta('POST', "#^$base$#", $c->emitir(...)),
            new Ruta('GET', "#^$base/(?<id>[1-9][0-9]{0,17})$#", $c->consultar(...)),
            new Ruta('POST', "#^$base/(?<id>[1-9][0-9]{0,17})/anulacion$#", $c->anular(...)),
        ];
    }
}

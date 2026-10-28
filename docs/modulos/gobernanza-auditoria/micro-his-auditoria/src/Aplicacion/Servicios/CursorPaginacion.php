<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\Servicios;

use Auditoria\Dominio\Modelo\Movimiento;

/**
 * Semana 7: el formato del cursor de R-09, fuera del caso de uso. Para el cliente es opaco:
 * base64url de «ocurrido_en UTC|id», sin relleno. No cambia de formato (REF-03): un cliente
 * puede tener guardado el cursor de una página anterior.
 */
final class CursorPaginacion
{
    public static function de(Movimiento $m): string
    {
        return rtrim(strtr(base64_encode($m->ocurridoEn->format('Y-m-d\TH:i:s\Z') . '|' . $m->id), '+/', '-_'), '=');
    }

    /** @return array{0: \DateTimeImmutable, 1: int}|null null si el cursor no es válido */
    public static function leer(string $cursor): ?array
    {
        $texto = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($texto === false || preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z)\|(\d{1,18})$/', $texto, $m) !== 1) {
            return null;
        }
        $fecha = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $m[1], new \DateTimeZone('UTC'));

        // Semana 15 · DEF-07-01: createFromFormat DESBORDA una fecha imposible (mes 13 → año siguiente); se exige que
        // la fecha leída, vuelta a formatear, sea exactamente el texto recibido (como FiltroRangoFechas::fecha()).
        return $fecha === false || $fecha->format('Y-m-d\TH:i:s\Z') !== $m[1] ? null : [$fecha, (int) $m[2]];
    }
}

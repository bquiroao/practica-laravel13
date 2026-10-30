<?php
declare(strict_types=1);

namespace Despues;

/** Responsabilidad única: decidir qué pasa con la versión anterior (RN-05). Sin acceso a datos. */
final class PoliticaVersionado
{
    /** @return array{0:int, 1:?string} [nueva versión, fecha de cierre de la anterior o null] */
    public static function decidir(?array $ultima, string $nuevoDesde): array
    {
        if ($ultima === null) {
            return [1, null];
        }
        $seSuperpone = $ultima['valid_to'] === null || $ultima['valid_to'] >= $nuevoDesde;
        if ($seSuperpone && $nuevoDesde <= $ultima['valid_from']) {
            throw new \DomainException('vigencia superpuesta');
        }
        $cierre = $seSuperpone ? date('Y-m-d', strtotime("$nuevoDesde -1 day")) : null;
        return [$ultima['version'] + 1, $cierre];
    }
}

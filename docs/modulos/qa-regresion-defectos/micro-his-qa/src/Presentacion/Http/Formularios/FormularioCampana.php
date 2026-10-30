<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Formularios;

use Qa\Presentacion\Http\Solicitud;

/** POST /campanas: nombre, funcionalidades, casos y umbral opcional. */
final class FormularioCampana extends Formulario
{
    /** @return array<string, mixed> */
    public static function desde(Solicitud $s): array
    {
        $d = self::objeto($s);
        self::tipos($d, ['nombre' => 'texto', 'funcionalidades' => 'lista', 'casos' => 'lista', 'umbral' => 'numero'],
            ['nombre', 'funcionalidades', 'casos']);

        return $d;
    }
}

<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Formularios;

use Qa\Presentacion\Http\Solicitud;

/** POST /campanas/{id}/inicio y /defectos/{id}/correccion: solo el build. */
final class FormularioBuild extends Formulario
{
    public static function desde(Solicitud $s): string
    {
        $d = self::objeto($s);
        self::tipos($d, ['build' => 'texto'], ['build']);

        return $d['build'];
    }
}

<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Formularios;

use Qa\Presentacion\Http\Solicitud;

/** POST /casos: la forma de un caso nuevo. */
final class FormularioCaso extends Formulario
{
    /** @return array<string, mixed> */
    public static function desde(Solicitud $s): array
    {
        $d = self::objeto($s);
        self::tipos($d, ['id' => 'texto', 'titulo' => 'texto', 'precondiciones' => 'texto', 'pasos' => 'lista', 'prioridad' => 'texto',
            'requisitos' => 'lista'], ['id', 'titulo', 'pasos', 'prioridad', 'requisitos']);

        return $d;
    }
}

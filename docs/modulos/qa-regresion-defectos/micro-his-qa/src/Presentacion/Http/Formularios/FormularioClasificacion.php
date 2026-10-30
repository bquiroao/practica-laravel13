<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Formularios;

use Qa\Presentacion\Http\Solicitud;

/** POST /defectos/{id}/clasificacion. */
final class FormularioClasificacion extends Formulario
{
    /** @return array<string, mixed> */
    public static function desde(Solicitud $s): array
    {
        $d = self::objeto($s);
        self::tipos($d, ['severidad' => 'texto', 'prioridad' => 'texto', 'motivo' => 'texto'], ['severidad', 'prioridad']);

        return $d;
    }
}

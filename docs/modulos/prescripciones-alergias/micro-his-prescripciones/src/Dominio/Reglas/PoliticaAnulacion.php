<?php

declare(strict_types=1);

namespace Prescripciones\Dominio\Reglas;

use Prescripciones\Dominio\Modelo\Prescripcion;

/**
 * Semana 6 (CU-07; actividad D-18 y D-19 de la semana 1): solo se anula una prescripción EMITIDA, y con un motivo
 * de al menos 10 caracteres sin contar los espacios de los extremos. El orden es parte de la regla: estado → motivo.
 * Deliberadamente NO consulta alergias: anular reduce el riesgo, así que no se bloquea si el Expediente no responde.
 */
final class PoliticaAnulacion
{
    public const MIN_MOTIVO = 10;

    /** @return string|null el código del rechazo, o null si se puede anular */
    public function rechazo(Prescripcion $prescripcion, string $motivo): ?string
    {
        if ($prescripcion->estado !== Prescripcion::EMITIDA) {
            return 'ESTADO_NO_PERMITE_ANULAR';
        }

        return mb_strlen(trim($motivo)) < self::MIN_MOTIVO ? 'MOTIVO_REQUERIDO' : null;
    }
}

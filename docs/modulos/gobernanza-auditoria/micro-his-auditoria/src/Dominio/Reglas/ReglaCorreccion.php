<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Reglas;

use Auditoria\Dominio\Modelo\Rechazo;
use Auditoria\Dominio\Modelo\SolicitudRegistro;

/**
 * Semana 6 — Forma de una corrección: «corrige_evento» (el event_id del original, del MISMO emisor)
 * y «motivo_correccion» (10 a 500 caracteres) van juntos o no van. Que el original exista, sea del
 * mismo hospital y no esté ya corregido lo comprueba el caso de uso dentro de la transacción.
 * Se agregó como una regla más: RegistrarMovimiento no cambió para validar la forma (OCP, semana 2).
 */
final class ReglaCorreccion implements ReglaMovimiento
{
    public function evaluar(SolicitudRegistro $s): ?Rechazo
    {
        $original = $s->datos['corrige_evento'] ?? null;
        $motivo = $s->datos['motivo_correccion'] ?? null;
        if ($original === null && $motivo === null) {
            return null;
        }
        if ($original === null) {
            return Rechazo::invalido(['motivo_correccion' => 'Solo va en una corrección (falta corrige_evento).']);
        }
        if (!is_string($original) || trim($original) === '' || $original === ($s->datos['event_id'] ?? null)) {
            return Rechazo::invalido(['corrige_evento' => 'El event_id de un movimiento anterior, distinto de este.']);
        }
        $largo = is_string($motivo) ? mb_strlen(trim($motivo)) : 0;
        if ($largo < 10 || $largo > 500) {
            return Rechazo::invalido(['motivo_correccion' => 'Obligatorio en una corrección: de 10 a 500 caracteres.']);
        }

        return null;
    }
}

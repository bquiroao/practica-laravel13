<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Reglas;

use Auditoria\Dominio\Modelo\Rechazo;
use Auditoria\Dominio\Modelo\SolicitudRegistro;

/** R-07: el detalle no lleva contraseñas, tokens, secretos ni datos clínicos, en ningún nivel. */
final class ReglaSinDatosProhibidos implements ReglaMovimiento
{
    private const PATRON = '/"(password|contrasena|token|secreto|clave|diagnostico)"\s*:/i';

    public function evaluar(SolicitudRegistro $s): ?Rechazo
    {
        return preg_match(self::PATRON, (string) json_encode($s->datos['detalle'] ?? [])) === 1
            ? Rechazo::invalido(['detalle' => 'Contiene datos prohibidos.']) : null;
    }
}

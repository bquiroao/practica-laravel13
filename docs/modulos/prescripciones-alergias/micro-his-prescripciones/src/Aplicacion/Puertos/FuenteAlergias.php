<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\Puertos;

use Prescripciones\Dominio\Modelo\ExpedienteAlergias;

/**
 * Puerto definido por el caso de uso (DIP): dice lo que la política necesita, no cómo se obtiene.
 *
 * Contrato que todo adaptador debe cumplir (R-07, fail-closed):
 *  - si no puede responder (base caída, tiempo agotado, hospital sin conexión), LANZA FuenteAlergiasNoDisponible;
 *  - NUNCA devuelve un expediente vacío para ocultar una falla.
 */
interface FuenteAlergias
{
    /** @throws FuenteAlergiasNoDisponible */
    public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias;
}

<?php

declare(strict_types=1);

namespace Prescripciones\Adaptadores\EnMemoria;

use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Dominio\ExpedienteAlergias;

/** Doble que simula el Expediente caído o lento: cumple el contrato lanzando la excepción. */
final class FuenteAlergiasCaida implements FuenteAlergias
{
    public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias
    {
        throw new FuenteAlergiasNoDisponible('Tiempo agotado consultando el expediente.');
    }
}

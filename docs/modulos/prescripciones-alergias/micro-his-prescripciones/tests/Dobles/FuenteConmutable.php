<?php

declare(strict_types=1);

use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;

/** Expediente que se puede «apagar» a mitad de una prueba (semana 6: anular no depende de él). */
final class FuenteConmutable implements FuenteAlergias
{
    public bool $caida = false;

    public function __construct(private readonly FuenteAlergias $interna)
    {
    }

    public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias
    {
        if ($this->caida) {
            throw new FuenteAlergiasNoDisponible('Expediente apagado por la prueba');
        }

        return $this->interna->alergiasDe($paciente, $hospital);
    }
}

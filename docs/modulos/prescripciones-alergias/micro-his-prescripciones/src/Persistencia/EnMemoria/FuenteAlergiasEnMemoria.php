<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia\EnMemoria;

use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;

final class FuenteAlergiasEnMemoria implements FuenteAlergias
{
    /** @param array<string, list<Alergia>> $porPaciente paciente => todas sus alergias, con su estado */
    public function __construct(private array $porPaciente = [])
    {
    }

    /** Lo que haría el Expediente al registrar una alergia (para demos y pruebas). */
    public function agregar(string $paciente, Alergia $alergia): void
    {
        $this->porPaciente[$paciente][] = $alergia;
    }

    public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias
    {
        $alergias = $this->porPaciente[$paciente] ?? [];

        return new ExpedienteAlergias($alergias !== [], $alergias);
    }
}

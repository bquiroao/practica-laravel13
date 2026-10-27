<?php

declare(strict_types=1);

namespace Prescripciones\Adaptadores\EnMemoria;

use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Dominio\Alergia;
use Prescripciones\Dominio\ExpedienteAlergias;

final class FuenteAlergiasEnMemoria implements FuenteAlergias
{
    /** @param array<string, list<Alergia>> $porPaciente paciente => alergias (todas, con su estado) */
    public function __construct(private array $porPaciente = [])
    {
    }

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

<?php

declare(strict_types=1);

namespace Prescripciones\Adaptadores\EnMemoria;

use Prescripciones\Aplicacion\Puertos\Pacientes;

final class PacientesEnMemoria implements Pacientes
{
    /** @param array<string, list<string>> $porHospital hospital => pacientes */
    public function __construct(private readonly array $porHospital)
    {
    }

    public function existeEnHospital(string $paciente, string $hospital): bool
    {
        return in_array($paciente, $this->porHospital[$hospital] ?? [], true);
    }
}

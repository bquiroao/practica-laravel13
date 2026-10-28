<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\Puertos;

interface Pacientes
{
    /** R-08: el paciente pertenece al hospital del token. */
    public function existeEnHospital(string $paciente, string $hospital): bool;
}

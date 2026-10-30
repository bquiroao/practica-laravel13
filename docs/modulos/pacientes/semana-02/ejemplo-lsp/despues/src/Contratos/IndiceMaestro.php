<?php

declare(strict_types=1);

namespace Pacientes\Contratos;

use Pacientes\Dominio\Paciente;

/** Índice maestro de pacientes (MPI central): detecta duplicados entre hospitales. */
interface IndiceMaestro
{
    /** Código del paciente que ya tiene ese documento, o null (R-02). */
    public function titularDe(string $claveIdentificador): ?string;

    /** @return list<string> códigos con el mismo nombre normalizado y la misma fecha de nacimiento (R-03) */
    public function coincidenciasProbables(string $nombreNormalizado, string $nacimiento): array;

    /** Agrega o reemplaza la entrada del paciente (ya con código). */
    public function indexar(Paciente $paciente): void;
}

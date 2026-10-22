<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Puertos;

use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Modelo\Identificador;

/** Índice maestro de pacientes (MPI): detecta duplicados en toda la red, no solo en un hospital. */
interface IndiceDuplicados
{
    /**
     * Código del paciente que ya tiene ese documento, o null (R-02).
     *
     * @throws ErrorPersistencia
     */
    public function titularDe(Identificador $documento): ?string;

    /**
     * Códigos con el mismo nombre normalizado y la misma fecha de nacimiento (R-03).
     *
     * @return list<string>
     * @throws ErrorPersistencia
     */
    public function coincidenciasProbables(string $nombreNormalizado, \DateTimeImmutable $nacimiento): array;

    /**
     * Semana 6: cuáles de estos códigos son de pacientes con identidad protegida (R-11).
     *
     * @param list<string> $codigos
     * @return list<string>
     * @throws ErrorPersistencia
     */
    public function protegidos(array $codigos): array;
}

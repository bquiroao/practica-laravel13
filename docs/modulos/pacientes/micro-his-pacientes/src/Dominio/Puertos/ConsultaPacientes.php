<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Puertos;

use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Modelo\CriterioBusqueda;
use Pacientes\Dominio\Modelo\Paciente;

/**
 * Lectura de pacientes (contrato de la semana 2).
 *  - Solo devuelve pacientes del hospital indicado; null si no existe EN ESE hospital.
 *  - buscar(): ordenados por apellidos y nombres, como máximo CriterioBusqueda::LIMITE.
 *  - No encontrar nada no es un error. Solo lanza ErrorPersistencia.
 */
interface ConsultaPacientes
{
    /** @throws ErrorPersistencia */
    public function porCodigo(string $hospital, string $codigo): ?Paciente;

    /**
     * @return list<Paciente>
     * @throws ErrorPersistencia
     */
    public function buscar(string $hospital, CriterioBusqueda $criterio): array;
}

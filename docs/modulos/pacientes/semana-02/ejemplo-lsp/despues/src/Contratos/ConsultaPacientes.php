<?php

declare(strict_types=1);

namespace Pacientes\Contratos;

use Pacientes\Dominio\CriterioBusqueda;
use Pacientes\Dominio\Paciente;

/**
 * Lectura de pacientes. La cumplen el repositorio principal, la réplica de lectura y la caché.
 *
 * Contrato (LSP):
 *  - porCodigo() solo devuelve pacientes del hospital indicado; si no existe EN ESE hospital, null.
 *  - buscar() solo devuelve pacientes del hospital indicado, que cumplen el criterio,
 *    ordenados por apellidos y nombres, como máximo CriterioBusqueda::LIMITE.
 *  - No encontrar nada no es un error: lista vacía o null, nunca una excepción.
 */
interface ConsultaPacientes
{
    public function porCodigo(string $hospital, string $codigo): ?Paciente;

    /** @return list<Paciente> */
    public function buscar(string $hospital, CriterioBusqueda $criterio): array;
}

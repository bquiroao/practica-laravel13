<?php

declare(strict_types=1);

namespace Pacientes\Contratos;

use Pacientes\Dominio\ConflictoVersion;
use Pacientes\Dominio\ErrorPaciente;
use Pacientes\Dominio\Paciente;

/**
 * Lectura y escritura de pacientes. Quien solo puede leer NO implementa esta interfaz.
 *
 * Contrato (LSP), además del de ConsultaPacientes:
 *  - registrar(): recibe un paciente válido sin código; devuelve el mismo paciente con un
 *    código único y versión 1. No lanza excepciones por un paciente válido.
 *  - actualizar(): si la versión guardada es $versionEsperada, guarda $cambios con versión + 1
 *    y lo devuelve. Si no lo es, lanza ConflictoVersion y NO cambia nada (R-06).
 *    Si el paciente no existe en su hospital, lanza ErrorPaciente 404.
 *    La comparación y la escritura son atómicas: es el único lugar sin carrera.
 *  - Después de actualizar(), porCodigo() devuelve lo guardado, nunca una copia anterior.
 */
interface RepositorioPacientes extends ConsultaPacientes
{
    public function registrar(Paciente $paciente): Paciente;

    /**
     * @throws ConflictoVersion
     * @throws ErrorPaciente 404 PACIENTE_NO_ENCONTRADO
     */
    public function actualizar(Paciente $cambios, int $versionEsperada): Paciente;
}

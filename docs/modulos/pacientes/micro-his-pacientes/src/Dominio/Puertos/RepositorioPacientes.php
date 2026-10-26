<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Puertos;

use Pacientes\Dominio\Excepciones\ConflictoVersion;
use Pacientes\Dominio\Excepciones\DocumentoDuplicado;
use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Excepciones\PacienteNoEncontrado;
use Pacientes\Dominio\Modelo\Paciente;

/**
 * Lectura y escritura (contrato de la semana 2).
 *  - registrar(): devuelve el paciente con código único y versión 1.
 *  - actualizar(): si la versión guardada es $versionEsperada, guarda con versión + 1;
 *    si no, ConflictoVersion y nada cambia. Comparar y escribir es atómico.
 *  - Después de actualizar(), porCodigo() devuelve lo guardado.
 */
interface RepositorioPacientes extends ConsultaPacientes
{
    /** @throws DocumentoDuplicado|ErrorPersistencia */
    public function registrar(Paciente $paciente): Paciente;

    /** @throws ConflictoVersion|PacienteNoEncontrado|DocumentoDuplicado|ErrorPersistencia */
    public function actualizar(Paciente $cambios, int $versionEsperada): Paciente;
}

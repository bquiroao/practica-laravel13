<?php

declare(strict_types=1);

namespace Pacientes\Antes;

use Pacientes\Dominio\Paciente;

/**
 * DISEÑO ANTES — violación V2 (precondición reforzada / excepción nueva).
 *
 * La réplica de lectura hereda del repositorio para reutilizar buscar(). Pero no puede
 * escribir, así que sobrescribe registrar() y actualizar() para lanzar una excepción que
 * el tipo base no anuncia. Quien recibe «un RepositorioPacientes» ya no puede confiar en
 * que registrar() funcione: tiene que preguntar con instanceof qué le pasaron.
 */
final class ReplicaPacientes extends RepositorioPacientes
{
    public static function copiaDe(RepositorioPacientes $principal): self
    {
        $replica = new self();
        [$replica->pacientes, $replica->secuencia] = $principal->instantanea();

        return $replica;
    }

    public function registrar(Paciente $paciente): Paciente
    {
        throw new \LogicException('La réplica es de solo lectura.');
    }

    public function actualizar(Paciente $cambios, int $versionEsperada): Paciente
    {
        throw new \LogicException('La réplica es de solo lectura.');
    }
}

<?php

declare(strict_types=1);

namespace Pacientes\Pruebas\Dobles;

use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Modelo\CriterioBusqueda;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Dominio\Puertos\RepositorioPacientes;
use Pacientes\Persistencia\EnMemoria\RepositorioPacientesEnMemoria;

/** Decorador: reutiliza el adaptador InMemory real y solo agrega «la base se cayó en X». */
final class RepositorioPacientesConFallas implements RepositorioPacientes
{
    /** @var list<string> operaciones que lanzan ErrorPersistencia */
    public array $fallarEn = [];

    public function __construct(public readonly RepositorioPacientesEnMemoria $real)
    {
    }

    public function porCodigo(string $hospital, string $codigo): ?Paciente
    {
        $this->quizasFallar('porCodigo');

        return $this->real->porCodigo($hospital, $codigo);
    }

    public function buscar(string $hospital, CriterioBusqueda $criterio): array
    {
        $this->quizasFallar('buscar');

        return $this->real->buscar($hospital, $criterio);
    }

    public function registrar(Paciente $paciente): Paciente
    {
        $this->quizasFallar('registrar');

        return $this->real->registrar($paciente);
    }

    public function actualizar(Paciente $cambios, int $versionEsperada): Paciente
    {
        $this->quizasFallar('actualizar');

        return $this->real->actualizar($cambios, $versionEsperada);
    }

    /** @return list<Paciente> */
    public function todos(): array
    {
        return $this->real->todos();
    }

    private function quizasFallar(string $operacion): void
    {
        if (in_array($operacion, $this->fallarEn, true)) {
            throw new ErrorPersistencia("Simulado: base no disponible en $operacion", '08006');
        }
    }
}

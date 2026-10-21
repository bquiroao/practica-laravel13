<?php

declare(strict_types=1);

namespace Pacientes\Pruebas\Dobles;

use Pacientes\Dominio\Excepciones\ConflictoVersion;
use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Excepciones\PacienteNoEncontrado;
use Pacientes\Dominio\Modelo\CriterioBusqueda;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Dominio\Puertos\RepositorioPacientes;

/** Doble en memoria que cumple el contrato y puede simular la base caída en una operación. */
final class RepositorioPacientesEnMemoria implements RepositorioPacientes, Restaurable
{
    /** @var array<string, Paciente> */
    private array $pacientes = [];
    private int $secuencia = 0;
    /** @var list<string> operaciones que lanzan ErrorPersistencia */
    public array $fallarEn = [];

    public function porCodigo(string $hospital, string $codigo): ?Paciente
    {
        $this->quizasFallar('porCodigo');
        $paciente = $this->pacientes[$codigo] ?? null;

        return $paciente !== null && $paciente->hospital === $hospital ? $paciente : null;
    }

    public function buscar(string $hospital, CriterioBusqueda $criterio): array
    {
        $this->quizasFallar('buscar');
        $encontrados = array_values(array_filter(
            $this->pacientes,
            static fn (Paciente $p): bool => $p->hospital === $hospital && $criterio->coincideCon($p),
        ));
        usort($encontrados, [Paciente::class, 'compararPorNombre']);

        return array_slice($encontrados, 0, CriterioBusqueda::LIMITE);
    }

    public function registrar(Paciente $paciente): Paciente
    {
        $this->quizasFallar('registrar');
        $codigo = sprintf('P-%06d', ++$this->secuencia);

        return $this->pacientes[$codigo] = $paciente->conCodigo($codigo, 1);
    }

    public function actualizar(Paciente $cambios, int $versionEsperada): Paciente
    {
        $this->quizasFallar('actualizar');
        $actual = $this->pacientes[(string) $cambios->codigo] ?? null;
        if ($actual === null || $actual->hospital !== $cambios->hospital) {
            throw new PacienteNoEncontrado();
        }
        if ($actual->version !== $versionEsperada) {
            throw new ConflictoVersion($actual->version);
        }

        return $this->pacientes[(string) $actual->codigo] = $cambios->conVersion($versionEsperada + 1);
    }

    /** @return list<Paciente> todos, de todos los hospitales (lo usa el índice de duplicados) */
    public function todos(): array
    {
        return array_values($this->pacientes);
    }

    public function instantanea(): mixed
    {
        return [$this->pacientes, $this->secuencia];
    }

    public function restaurar(mixed $instantanea): void
    {
        [$this->pacientes, $this->secuencia] = $instantanea;
    }

    private function quizasFallar(string $operacion): void
    {
        if (in_array($operacion, $this->fallarEn, true)) {
            throw new ErrorPersistencia("Simulado: base no disponible en $operacion", '08006');
        }
    }
}

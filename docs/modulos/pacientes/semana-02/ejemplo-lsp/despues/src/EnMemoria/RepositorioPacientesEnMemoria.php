<?php

declare(strict_types=1);

namespace Pacientes\EnMemoria;

use Pacientes\Contratos\RepositorioPacientes;
use Pacientes\Dominio\ConflictoVersion;
use Pacientes\Dominio\CriterioBusqueda;
use Pacientes\Dominio\ErrorPaciente;
use Pacientes\Dominio\Paciente;

/** Repositorio principal en memoria. En el HIS sería PDO sobre local_patients. */
final class RepositorioPacientesEnMemoria implements RepositorioPacientes, Restaurable
{
    /** @var array<string, Paciente> */
    private array $pacientes = [];
    private int $secuencia = 0;

    public function porCodigo(string $hospital, string $codigo): ?Paciente
    {
        $paciente = $this->pacientes[$codigo] ?? null;

        return $paciente !== null && $paciente->hospital === $hospital ? $paciente : null;
    }

    public function buscar(string $hospital, CriterioBusqueda $criterio): array
    {
        $encontrados = array_values(array_filter(
            $this->pacientes,
            static fn (Paciente $p): bool => $p->hospital === $hospital && $criterio->coincideCon($p),
        ));
        usort($encontrados, [Paciente::class, 'compararPorNombre']);

        return array_slice($encontrados, 0, CriterioBusqueda::LIMITE);
    }

    public function registrar(Paciente $paciente): Paciente
    {
        $codigo = sprintf('P-%06d', ++$this->secuencia);

        return $this->pacientes[$codigo] = $paciente->conCodigo($codigo, 1);
    }

    public function actualizar(Paciente $cambios, int $versionEsperada): Paciente
    {
        $actual = $this->porCodigo($cambios->hospital, (string) $cambios->codigo);
        if ($actual === null) {
            throw new ErrorPaciente(404, 'PACIENTE_NO_ENCONTRADO');
        }
        // En SQL: UPDATE ... SET version = version + 1 WHERE codigo = ? AND version = ?
        if ($actual->version !== $versionEsperada) {
            throw new ConflictoVersion($actual->version);
        }

        return $this->pacientes[$actual->codigo] = $cambios->conVersion($versionEsperada + 1);
    }

    public function instantanea(): mixed
    {
        return [$this->pacientes, $this->secuencia];
    }

    public function restaurar(mixed $instantanea): void
    {
        [$this->pacientes, $this->secuencia] = $instantanea;
    }
}

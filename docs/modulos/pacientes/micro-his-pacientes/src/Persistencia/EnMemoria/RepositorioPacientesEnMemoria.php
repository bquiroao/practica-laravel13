<?php

declare(strict_types=1);

namespace Pacientes\Persistencia\EnMemoria;

use Pacientes\Dominio\Excepciones\ConflictoVersion;
use Pacientes\Dominio\Excepciones\DocumentoDuplicado;
use Pacientes\Dominio\Excepciones\PacienteNoEncontrado;
use Pacientes\Dominio\Modelo\CriterioBusqueda;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Dominio\Puertos\RepositorioPacientes;

/**
 * Adaptador InMemory de RepositorioPacientes. Cumple el MISMO contrato que el adaptador
 * PDO (tests/Contratos): versión atómica, documento único, búsqueda acotada al hospital.
 */
final class RepositorioPacientesEnMemoria implements RepositorioPacientes, Restaurable
{
    /** @var array<string, Paciente> código => paciente, de todos los hospitales */
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
        $this->exigirDocumentoLibre($paciente, null);
        $codigo = sprintf('P-%06d', ++$this->secuencia);

        return $this->pacientes[$codigo] = $paciente->conCodigo($codigo, 1);
    }

    public function actualizar(Paciente $cambios, int $versionEsperada): Paciente
    {
        $actual = $this->porCodigo($cambios->hospital, (string) $cambios->codigo);
        if ($actual === null) {
            throw new PacienteNoEncontrado();
        }
        if ($actual->version !== $versionEsperada) {
            throw new ConflictoVersion($actual->version);
        }
        $this->exigirDocumentoLibre($cambios, $actual->codigo);

        return $this->pacientes[(string) $actual->codigo] = $cambios->conVersion($versionEsperada + 1);
    }

    /** @return list<Paciente> todos los pacientes de todos los hospitales (para el índice de duplicados) */
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

    /** Equivalente del índice único pacientes_documento_unico de PostgreSQL. */
    private function exigirDocumentoLibre(Paciente $paciente, ?string $excepto): void
    {
        $canonico = $paciente->identificador?->canonico();
        if ($canonico === null) {
            return;
        }
        foreach ($this->pacientes as $codigo => $otro) {
            if ($codigo !== $excepto && $otro->identificador?->canonico() === $canonico) {
                throw new DocumentoDuplicado();
            }
        }
    }
}

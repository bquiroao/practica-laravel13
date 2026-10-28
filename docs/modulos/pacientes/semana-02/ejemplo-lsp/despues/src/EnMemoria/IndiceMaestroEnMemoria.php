<?php

declare(strict_types=1);

namespace Pacientes\EnMemoria;

use Pacientes\Contratos\IndiceMaestro;
use Pacientes\Dominio\Paciente;

/** MPI central en memoria. En el HIS: mpi_identifiers (search_hash) e identity_match_candidates. */
final class IndiceMaestroEnMemoria implements IndiceMaestro, Restaurable
{
    /** @var array<string, string> clave del documento => código */
    private array $porClave = [];
    /** @var array<string, array{nombre: string, nacimiento: string, clave: ?string}> código => datos */
    private array $personas = [];

    public function titularDe(string $claveIdentificador): ?string
    {
        return $this->porClave[$claveIdentificador] ?? null;
    }

    public function coincidenciasProbables(string $nombreNormalizado, string $nacimiento): array
    {
        $codigos = [];
        foreach ($this->personas as $codigo => $persona) {
            if ($persona['nombre'] === $nombreNormalizado && $persona['nacimiento'] === $nacimiento) {
                $codigos[] = (string) $codigo;
            }
        }

        return $codigos;
    }

    public function indexar(Paciente $paciente): void
    {
        $codigo = (string) $paciente->codigo;
        $anterior = $this->personas[$codigo]['clave'] ?? null;
        if ($anterior !== null && ($this->porClave[$anterior] ?? null) === $codigo) {
            unset($this->porClave[$anterior]);
        }

        $clave = $paciente->identificador?->claveBusqueda();
        if ($clave !== null) {
            $this->porClave[$clave] = $codigo;
        }
        $this->personas[$codigo] = [
            'nombre' => $paciente->nombreNormalizado(),
            'nacimiento' => $paciente->nacimiento->format('Y-m-d'),
            'clave' => $clave,
        ];
    }

    public function instantanea(): mixed
    {
        return [$this->porClave, $this->personas];
    }

    public function restaurar(mixed $instantanea): void
    {
        [$this->porClave, $this->personas] = $instantanea;
    }
}

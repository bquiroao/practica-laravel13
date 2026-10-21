<?php

declare(strict_types=1);

namespace Pacientes\Pruebas\Dobles;

use Pacientes\Dominio\Modelo\Identificador;
use Pacientes\Dominio\Puertos\IndiceDuplicados;

/** Índice de duplicados sobre el repositorio en memoria, en TODOS los hospitales. */
final class IndiceDuplicadosEnMemoria implements IndiceDuplicados
{
    public function __construct(private readonly RepositorioPacientesEnMemoria $repositorio)
    {
    }

    public function titularDe(Identificador $documento): ?string
    {
        foreach ($this->repositorio->todos() as $paciente) {
            if ($paciente->identificador?->canonico() === $documento->canonico()) {
                return $paciente->codigo;
            }
        }

        return null;
    }

    public function coincidenciasProbables(string $nombreNormalizado, \DateTimeImmutable $nacimiento): array
    {
        $codigos = [];
        foreach ($this->repositorio->todos() as $paciente) {
            if ($paciente->nombreNormalizado() === $nombreNormalizado && $paciente->nacimiento == $nacimiento) {
                $codigos[] = (string) $paciente->codigo;
            }
        }

        return $codigos;
    }
}

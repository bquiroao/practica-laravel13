<?php
declare(strict_types=1);

namespace Admision\Persistence\InMemory;

use Admision\Domain\Modelo\CodigoPaciente;
use Admision\Domain\Modelo\TenantId;
use Admision\Domain\Repositorio\DirectorioPacientes;

final class InMemoryDirectorioPacientes implements DirectorioPacientes
{
    /** @param list<array{string,string,bool}> $filas [hospital, código, activo] */
    public function __construct(private readonly array $filas)
    {
    }

    public function existeActivo(TenantId $tenantId, CodigoPaciente $paciente): bool
    {
        foreach ($this->filas as [$hospital, $codigo, $activo]) {
            if ($hospital === $tenantId->valor && $codigo === $paciente->valor) {
                return $activo;
            }
        }
        return false;
    }
}

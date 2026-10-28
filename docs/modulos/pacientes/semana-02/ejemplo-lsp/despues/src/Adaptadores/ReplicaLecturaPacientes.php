<?php

declare(strict_types=1);

namespace Pacientes\Adaptadores;

use Pacientes\Contratos\ConsultaPacientes;
use Pacientes\Dominio\CriterioBusqueda;
use Pacientes\Dominio\Paciente;

/**
 * Réplica de solo lectura para descargar las búsquedas (RNF-06).
 *
 * Implementa SOLO ConsultaPacientes: como no puede escribir, no promete escribir.
 * Así no existe un registrar() que lance «solo lectura» y el sistema de tipos impide
 * inyectarla en un servicio que escribe. En el HIS sería una conexión PDO a la réplica;
 * aquí lee de la fuente que recibe.
 */
final class ReplicaLecturaPacientes implements ConsultaPacientes
{
    public function __construct(private readonly ConsultaPacientes $fuente)
    {
    }

    public function porCodigo(string $hospital, string $codigo): ?Paciente
    {
        return $this->fuente->porCodigo($hospital, $codigo);
    }

    public function buscar(string $hospital, CriterioBusqueda $criterio): array
    {
        return $this->fuente->buscar($hospital, $criterio);
    }
}

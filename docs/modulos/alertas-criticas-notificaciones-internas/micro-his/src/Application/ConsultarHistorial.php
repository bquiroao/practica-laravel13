<?php
declare(strict_types=1);

namespace AlertasCriticas\Application;

use AlertasCriticas\Domain\{Alerta, AlertaNoEncontradaException, AlertaRepository};

/** CU-07: historial completo de una alerta del tenant del usuario (RN-10, RNF-01). */
final class ConsultarHistorial
{
    public function __construct(private AlertaRepository $repositorio) {}

    public function ejecutar(string $idAlerta, string $tenant): Alerta
    {
        return $this->repositorio->buscar($idAlerta, $tenant)
            ?? throw new AlertaNoEncontradaException("No existe la alerta $idAlerta en el tenant $tenant");
    }
}

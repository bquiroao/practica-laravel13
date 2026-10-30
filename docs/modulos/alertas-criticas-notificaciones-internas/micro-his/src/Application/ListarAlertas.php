<?php
declare(strict_types=1);

namespace AlertasCriticas\Application;

use AlertasCriticas\Domain\{Alerta, AlertaRepository};

/** Lista las alertas del tenant del usuario (RNF-01), más recientes primero. */
final class ListarAlertas
{
    public function __construct(private AlertaRepository $repositorio) {}

    /** @return Alerta[] */
    public function ejecutar(string $tenant): array
    {
        return $this->repositorio->listar($tenant);
    }
}

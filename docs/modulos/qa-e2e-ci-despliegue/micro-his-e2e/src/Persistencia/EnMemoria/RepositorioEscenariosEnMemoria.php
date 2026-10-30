<?php

declare(strict_types=1);

namespace E2e\Persistencia\EnMemoria;

use E2e\Dominio\Modelo\EscenarioE2E;
use E2e\Dominio\Puertos\RepositorioEscenarios;

final class RepositorioEscenariosEnMemoria implements RepositorioEscenarios
{
    /** @var array<string, EscenarioE2E> tenant|id => escenario (inmutable) */
    private array $filas = [];

    public function guardar(EscenarioE2E $escenario): void
    {
        $this->filas[$escenario->tenant . '|' . $escenario->id] = $escenario;
    }

    public function porId(string $tenant, string $id): ?EscenarioE2E
    {
        return $this->filas["$tenant|$id"] ?? null;
    }

    public function delTenant(string $tenant): array
    {
        $r = array_values(array_filter($this->filas, static fn (EscenarioE2E $e): bool => $e->tenant === $tenant));
        usort($r, static fn (EscenarioE2E $a, EscenarioE2E $b): int => strcmp($a->id, $b->id));

        return $r;
    }

    public function instantanea(): mixed
    {
        return $this->filas;
    }

    public function restaurar(mixed $estado): void
    {
        $this->filas = $estado;
    }
}

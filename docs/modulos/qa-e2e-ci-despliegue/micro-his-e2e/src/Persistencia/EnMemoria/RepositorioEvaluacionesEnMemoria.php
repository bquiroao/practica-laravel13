<?php

declare(strict_types=1);

namespace E2e\Persistencia\EnMemoria;

use E2e\Dominio\Modelo\Decision;
use E2e\Dominio\Puertos\RepositorioEvaluaciones;

final class RepositorioEvaluacionesEnMemoria implements RepositorioEvaluaciones
{
    /** @var list<Decision> */
    private array $todas = [];

    public function guardar(Decision $decision): void
    {
        $this->todas[] = $decision;
    }

    public function deVersion(string $tenant, string $version): array
    {
        return array_values(array_filter($this->todas, static fn (Decision $d): bool => $d->tenant === $tenant && $d->version === $version));
    }

    public function instantanea(): mixed
    {
        return $this->todas;
    }

    public function restaurar(mixed $estado): void
    {
        $this->todas = $estado;
    }
}

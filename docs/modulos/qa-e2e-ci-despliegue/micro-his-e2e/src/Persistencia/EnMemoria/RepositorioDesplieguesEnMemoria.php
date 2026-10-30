<?php

declare(strict_types=1);

namespace E2e\Persistencia\EnMemoria;

use E2e\Dominio\Modelo\Despliegue;
use E2e\Dominio\Puertos\RepositorioDespliegues;

final class RepositorioDesplieguesEnMemoria implements RepositorioDespliegues
{
    /** @var list<Despliegue> */
    private array $todos = [];

    public function registrar(Despliegue $despliegue): Despliegue
    {
        $d = $despliegue->conId(count($this->todos) + 1);
        $this->todos[] = $d;

        return $d;
    }

    public function deVersion(string $tenant, string $version): array
    {
        return array_values(array_filter($this->todos, static fn (Despliegue $d): bool => $d->tenant === $tenant && $d->version === $version));
    }

    public function vigente(string $tenant, string $entorno): ?Despliegue
    {
        $vigente = null;
        foreach ($this->todos as $d) {
            if ($d->tenant === $tenant && $d->entorno === $entorno && $d->estado === Despliegue::DESPLEGADO) {
                $vigente = $d;
            }
        }

        return $vigente;
    }

    public function instantanea(): mixed
    {
        return $this->todos;
    }

    public function restaurar(mixed $estado): void
    {
        $this->todos = $estado;
    }
}

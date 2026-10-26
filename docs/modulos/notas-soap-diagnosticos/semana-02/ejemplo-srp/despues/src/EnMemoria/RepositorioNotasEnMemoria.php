<?php

declare(strict_types=1);

namespace NotasSoap\EnMemoria;

use NotasSoap\Contratos\RepositorioNotas;
use NotasSoap\Dominio\NotaSoap;

final class RepositorioNotasEnMemoria implements RepositorioNotas, Restaurable
{
    /** @var array<string, NotaSoap> */
    private array $notas = [];
    private int $secuencia = 0;

    public function guardar(NotaSoap $nota): NotaSoap
    {
        $guardada = $nota->conId(sprintf('nota-%03d', ++$this->secuencia));
        $this->notas[(string) $guardada->id] = $guardada;

        return $guardada;
    }

    public function buscar(string $id): ?NotaSoap
    {
        return $this->notas[$id] ?? null;
    }

    public function actualizar(NotaSoap $nota): void
    {
        $this->notas[(string) $nota->id] = $nota;
    }

    public function total(): int
    {
        return count($this->notas);
    }

    public function instantanea(): array
    {
        return [$this->notas, $this->secuencia];
    }

    public function restaurar(array $estado): void
    {
        [$this->notas, $this->secuencia] = $estado;
    }
}

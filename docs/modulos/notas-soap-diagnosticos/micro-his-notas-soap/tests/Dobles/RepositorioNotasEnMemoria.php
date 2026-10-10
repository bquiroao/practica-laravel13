<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Puertos\RepositorioNotas;

final class RepositorioNotasEnMemoria implements RepositorioNotas, Restaurable
{
    /** @var array<string, NotaSoap> */
    public array $notas = [];

    public function guardar(NotaSoap $nota): void
    {
        $this->notas[$nota->id] = $nota;
    }

    public function buscar(string $id): ?NotaSoap
    {
        return $this->notas[$id] ?? null;
    }

    public function registrarFirma(NotaSoap $nota): void
    {
        $this->notas[$nota->id] = $nota;
    }

    public function instantanea(): array
    {
        return $this->notas;
    }

    public function restaurar(array $estado): void
    {
        $this->notas = $estado;
    }
}

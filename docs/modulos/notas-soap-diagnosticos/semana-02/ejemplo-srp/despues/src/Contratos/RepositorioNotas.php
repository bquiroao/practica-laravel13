<?php

declare(strict_types=1);

namespace NotasSoap\Contratos;

use NotasSoap\Dominio\NotaSoap;

/** Persistencia de notas (clinical_notes). */
interface RepositorioNotas
{
    public function guardar(NotaSoap $nota): NotaSoap;

    public function buscar(string $id): ?NotaSoap;

    public function actualizar(NotaSoap $nota): void;
}

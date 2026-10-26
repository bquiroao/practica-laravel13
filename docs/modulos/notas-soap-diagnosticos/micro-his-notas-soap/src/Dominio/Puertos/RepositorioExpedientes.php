<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Puertos;

/** R-03: expediente abierto y del mismo paciente que el episodio. */
interface RepositorioExpedientes
{
    public function estaDisponible(string $expedienteId, string $episodioId): bool;
}

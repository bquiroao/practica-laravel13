<?php

declare(strict_types=1);

namespace NotasSoap\Contratos;

/** R-03: expediente abierto y del mismo paciente que el episodio (medical_records, care_episodes). */
interface RepositorioExpedientes
{
    public function estaDisponible(string $expedienteId, string $episodioId): bool;
}

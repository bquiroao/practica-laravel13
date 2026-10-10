<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\Pdo;

use NotasSoap\Dominio\Puertos\RepositorioExpedientes;

/** R-03 contra medical_records y care_episodes. */
final class PdoRepositorioExpedientes extends RepositorioPdo implements RepositorioExpedientes
{
    public function estaDisponible(string $expedienteId, string $episodioId): bool
    {
        return $this->ejecutar(
            "SELECT 1
               FROM medical_records r
               JOIN care_episodes e ON e.local_patient_id = r.local_patient_id
              WHERE r.medical_record_id = :expediente
                AND e.care_episode_id = :episodio
                AND r.status = 'open'",
            ['expediente' => $expedienteId, 'episodio' => $episodioId],
        )->fetchColumn() !== false;
    }
}

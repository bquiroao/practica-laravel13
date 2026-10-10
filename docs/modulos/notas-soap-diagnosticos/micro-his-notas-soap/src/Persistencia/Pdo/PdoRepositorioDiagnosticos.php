<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\Pdo;

use DateTimeImmutable;
use NotasSoap\Dominio\Modelo\Diagnostico;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;

/** diagnoses ligados a una nota. */
final class PdoRepositorioDiagnosticos extends RepositorioPdo implements RepositorioDiagnosticos
{
    public function guardar(Diagnostico $diagnostico): void
    {
        $this->ejecutar(
            "INSERT INTO diagnoses
                (diagnosis_id, care_episode_id, clinical_note_id, author_user_ref_id, code, description, type, status, onset_date)
             VALUES (:id, :episodio, :nota, :autor, :codigo, :descripcion, :tipo, 'active', :inicio)",
            [
                'id' => $diagnostico->id,
                'episodio' => $diagnostico->episodioId,
                'nota' => $diagnostico->notaId,
                'autor' => $diagnostico->autorId,
                'codigo' => $diagnostico->codigo,
                'descripcion' => $diagnostico->descripcion,
                'tipo' => $diagnostico->tipo->value,
                'inicio' => $diagnostico->fechaInicio->format('Y-m-d'),
            ],
        );
    }

    public function deNota(string $notaId): array
    {
        $filas = $this->ejecutar(
            'SELECT diagnosis_id, clinical_note_id, care_episode_id, author_user_ref_id, code, description, type, onset_date
               FROM diagnoses
              WHERE clinical_note_id = :nota
              ORDER BY diagnosis_id',
            ['nota' => $notaId],
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $f): Diagnostico => Diagnostico::reconstruir(
            (string) $f['diagnosis_id'],
            (string) $f['clinical_note_id'],
            (string) $f['care_episode_id'],
            (string) $f['author_user_ref_id'],
            (string) $f['code'],
            (string) $f['description'],
            (string) $f['type'],
            new DateTimeImmutable((string) $f['onset_date']),
        ), $filas);
    }
}

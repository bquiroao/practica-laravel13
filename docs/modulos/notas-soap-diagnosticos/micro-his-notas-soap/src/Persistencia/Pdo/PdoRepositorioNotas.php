<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\Pdo;

use DateTimeImmutable;
use NotasSoap\Dominio\Excepciones\NotaFirmada;
use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Modelo\SeccionesSoap;
use NotasSoap\Dominio\Puertos\RepositorioNotas;

/** clinical_notes con note_type = 'soap'. */
final class PdoRepositorioNotas extends RepositorioPdo implements RepositorioNotas
{
    public function guardar(NotaSoap $nota): void
    {
        $this->ejecutar(
            "INSERT INTO clinical_notes
                (clinical_note_id, medical_record_id, care_episode_id, author_user_ref_id, note_type, content, status, version_no, created_at)
             VALUES (:id, :expediente, :episodio, :autor, 'soap', :contenido, :estado, :version, :creada)",
            [
                'id' => $nota->id,
                'expediente' => $nota->expedienteId,
                'episodio' => $nota->episodioId,
                'autor' => $nota->autorId,
                'contenido' => $nota->secciones->comoContenido(),
                'estado' => $nota->estado,
                'version' => $nota->version,
                'creada' => self::fecha($nota->creadaEn),
            ],
        );
    }

    public function buscar(string $id): ?NotaSoap
    {
        $fila = $this->ejecutar(
            "SELECT clinical_note_id, medical_record_id, care_episode_id, author_user_ref_id, content, status, version_no, created_at, signed_at
               FROM clinical_notes
              WHERE clinical_note_id = :id AND note_type = 'soap'",
            ['id' => $id],
        )->fetch(\PDO::FETCH_ASSOC);

        return $fila === false ? null : self::aNota($fila);
    }

    public function deExpediente(string $expedienteId): array
    {
        $filas = $this->ejecutar(
            "SELECT clinical_note_id, medical_record_id, care_episode_id, author_user_ref_id, content, status, version_no, created_at, signed_at
               FROM clinical_notes
              WHERE medical_record_id = :expediente AND note_type = 'soap'
              ORDER BY created_at, clinical_note_id",
            ['expediente' => $expedienteId],
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(self::aNota(...), $filas);
    }

    /** @param array<string, mixed> $fila */
    private static function aNota(array $fila): NotaSoap
    {
        return new NotaSoap(
            (string) $fila['clinical_note_id'],
            (string) $fila['medical_record_id'],
            (string) $fila['care_episode_id'],
            (string) $fila['author_user_ref_id'],
            SeccionesSoap::desdeContenido((string) $fila['content']),
            (int) $fila['version_no'],
            (string) $fila['status'],
            new DateTimeImmutable((string) $fila['created_at']),
            $fila['signed_at'] === null ? null : new DateTimeImmutable((string) $fila['signed_at']),
        );
    }

    public function registrarFirma(NotaSoap $nota): void
    {
        $sentencia = $this->ejecutar(
            "UPDATE clinical_notes
                SET status = :estado, signed_at = :firmada
              WHERE clinical_note_id = :id AND status = 'draft'",
            ['estado' => $nota->estado, 'firmada' => $nota->firmadaEn === null ? null : self::fecha($nota->firmadaEn), 'id' => $nota->id],
        );

        // Si otra petición la firmó primero, la condición status = 'draft' ya no se cumple.
        if ($sentencia->rowCount() === 0) {
            throw new NotaFirmada('La nota ya está firmada y no se puede modificar.');
        }
    }
}

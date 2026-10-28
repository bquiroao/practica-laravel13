<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\Pdo;

use NotasSoap\Aplicacion\Puertos\RegistroIdempotencia;

/** idempotency_keys: una fila por (hospital, usuario, clave). La llave primaria impide duplicados. */
final class PdoRegistroIdempotencia extends RepositorioPdo implements RegistroIdempotencia
{
    public function buscar(string $hospitalId, string $usuarioId, string $clave): ?array
    {
        $fila = $this->ejecutar(
            'SELECT request_hash, clinical_note_id FROM idempotency_keys
              WHERE hospital_id = :hospital AND user_ref_id = :usuario AND idempotency_key = :clave',
            ['hospital' => $hospitalId, 'usuario' => $usuarioId, 'clave' => $clave],
        )->fetch(\PDO::FETCH_ASSOC);

        return $fila === false ? null : ['huella' => (string) $fila['request_hash'], 'nota_id' => (string) $fila['clinical_note_id']];
    }

    public function guardar(string $hospitalId, string $usuarioId, string $clave, string $huella, string $notaId): void
    {
        $this->ejecutar(
            'INSERT INTO idempotency_keys (hospital_id, user_ref_id, idempotency_key, request_hash, clinical_note_id, created_at)
             VALUES (:hospital, :usuario, :clave, :huella, :nota, :fecha)',
            ['hospital' => $hospitalId, 'usuario' => $usuarioId, 'clave' => $clave, 'huella' => $huella, 'nota' => $notaId,
                'fecha' => self::fecha(new \DateTimeImmutable())],
        );
    }
}

<?php
declare(strict_types=1);

namespace Alergias\Persistencia;

use Alergias\Dominio\Alergeno;
use Alergias\Dominio\Alergia;
use Alergias\Dominio\AlergiaDuplicada;
use Alergias\Dominio\ErrorPersistencia;
use Alergias\Dominio\RepositorioAlergias;
use Alergias\Dominio\Severidad;
use Alergias\Dominio\TipoAlergia;
use DateTimeImmutable;
use PDO;
use PDOException;

/** Adaptador PDO sobre la tabla real allergies de HOSPITAL. Siempre prepare() + execute(). */
final class PdoRepositorioAlergias implements RepositorioAlergias
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function existeActivaEquivalente(string $pacienteId, TipoAlergia $tipo, string $claveAlergeno): bool
    {
        return (bool) $this->consultar(
            "SELECT 1 FROM allergies WHERE local_patient_id = :p AND allergy_type = :t AND status = 'active'
               AND lower(COALESCE(NULLIF(btrim(allergen_code), ''), NULLIF(btrim(allergen_name), ''))) = :c",
            ['p' => $pacienteId, 't' => $tipo->value, 'c' => $claveAlergeno],
        )->fetchColumn();
    }

    public function guardar(Alergia $a): void
    {
        try {
            $sql = $this->pdo->prepare(
                'INSERT INTO allergies (allergy_id, local_patient_id, verified_by_user_ref_id, allergen_code, allergen_name,
                    reaction, allergy_type, severity, status, onset_date)
                 VALUES (:id, :p, :v, :cod, :nom, :r, :t, :s, :e, :ini)'
            );
            $sql->execute([
                'id' => $a->id, 'p' => $a->pacienteId, 'v' => $a->verificadaPor,
                'cod' => $a->alergeno->codigo, 'nom' => $a->alergeno->nombre, 'r' => $a->reaccion,
                't' => $a->tipo->value, 's' => $a->severidad->value, 'e' => $a->estado,
                'ini' => $a->inicio?->format('Y-m-d'),
            ]);
        } catch (PDOException $e) {
            // 23505 = violación del índice allergies_active_equivalent_unique (dos registros a la vez)
            throw $e->getCode() === '23505' ? new AlergiaDuplicada() : new ErrorPersistencia('No se pudo guardar la alergia.', 0, $e);
        }
    }

    public function activasDePaciente(string $pacienteId): array
    {
        $filas = $this->consultar(
            "SELECT * FROM allergies WHERE local_patient_id = :p AND status = 'active' ORDER BY recorded_at",
            ['p' => $pacienteId],
        )->fetchAll();
        return array_map(fn (array $f) => Alergia::reconstruir(
            $f['allergy_id'], $f['local_patient_id'], new Alergeno($f['allergen_code'], $f['allergen_name']),
            TipoAlergia::from($f['allergy_type']), Severidad::from($f['severity']), $f['reaction'],
            $f['onset_date'] ? new DateTimeImmutable($f['onset_date']) : null, $f['verified_by_user_ref_id'], $f['status'],
        ), $filas);
    }

    private function consultar(string $sql, array $parametros): \PDOStatement
    {
        try {
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($parametros);
            return $sentencia;
        } catch (PDOException $e) {
            throw new ErrorPersistencia('No se pudo leer alergias.', 0, $e);
        }
    }
}

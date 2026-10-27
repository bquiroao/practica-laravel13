<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia\Pdo;

use Prescripciones\Dominio\Excepciones\ClaveEnUso;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;
use Prescripciones\Dominio\Modelo\Hallazgo;
use Prescripciones\Dominio\Modelo\Prescripcion;
use Prescripciones\Dominio\Modelo\ResultadoVerificacion;
use Prescripciones\Dominio\Puertos\RepositorioPrescripciones;

/** Prescripciones e ítems con sentencias preparadas. Solo SELECT e INSERT: la base además prohíbe UPDATE y DELETE. */
final class PdoRepositorioPrescripciones implements RepositorioPrescripciones
{
    private const COLUMNAS = 'prescription_id, hospital, local_patient_id, prescriber, idempotency_key, content_hash, status,
        allergy_check_result, allergy_findings, allergens_checked, override_justification, no_known_allergies_confirmed, issued_at';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function porClave(string $hospital, string $clave): ?Prescripcion
    {
        return $this->una('SELECT ' . self::COLUMNAS . ' FROM prescriptions WHERE hospital = :hospital AND idempotency_key = :clave',
            ['hospital' => $hospital, 'clave' => $clave]);
    }

    public function porId(string $hospital, int $id): ?Prescripcion
    {
        return $this->una('SELECT ' . self::COLUMNAS . ' FROM prescriptions WHERE hospital = :hospital AND prescription_id = :id',
            ['hospital' => $hospital, 'id' => $id]);
    }

    public function guardar(Prescripcion $p): Prescripcion
    {
        try {
            $insertar = $this->pdo->prepare('INSERT INTO prescriptions (hospital, local_patient_id, prescriber, idempotency_key, content_hash,
                    allergy_check_result, allergy_findings, allergens_checked, override_justification, no_known_allergies_confirmed, issued_at)
                VALUES (:hospital, :paciente, :prescriptor, :clave, :huella, :resultado, :hallazgos, :alergenos, :justificacion, :confirma, :emitida)
                RETURNING prescription_id');
            $insertar->bindValue('hospital', $p->hospital);
            $insertar->bindValue('paciente', $p->paciente);
            $insertar->bindValue('prescriptor', $p->prescriptor);
            $insertar->bindValue('clave', $p->clave);
            $insertar->bindValue('huella', $p->huella);
            $insertar->bindValue('resultado', $p->verificacion->estado);
            $insertar->bindValue('hallazgos', json_encode($p->verificacion->hallazgosComoArreglo(), \JSON_THROW_ON_ERROR));
            $insertar->bindValue('alergenos', json_encode($p->alergenosEvaluados, \JSON_THROW_ON_ERROR));
            $insertar->bindValue('justificacion', $p->justificacion);
            $insertar->bindValue('confirma', $p->confirmaSinAlergias, \PDO::PARAM_BOOL);
            $insertar->bindValue('emitida', $p->emitidaEn->format('Y-m-d\TH:i:sP'));
            $insertar->execute();
            $id = (int) $insertar->fetchColumn();

            $item = $this->pdo->prepare('INSERT INTO prescription_items (prescription_id, line_no, medication_code, dose, unit, frequency_hours, route, duration_days)
                VALUES (:id, :linea, :medicamento, :dosis, :unidad, :frecuencia, :via, :dias)');
            foreach (array_values($p->items) as $i => $it) {
                $item->execute(['id' => $id, 'linea' => $i + 1, 'medicamento' => $it['medicamento'], 'dosis' => $it['dosis'], 'unidad' => $it['unidad'],
                    'frecuencia' => $it['frecuencia_horas'], 'via' => $it['via'], 'dias' => $it['dias']]);
            }

            return $p->conId($id);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'prescriptions_idempotency_unique')) {
                throw new ClaveEnUso('La clave de idempotencia ya se usó en este hospital.', 0, $e);
            }
            throw new ErrorPersistencia('No se pudo guardar la prescripción.', 0, $e);
        }
    }

    /** @param array<string, mixed> $parametros */
    private function una(string $sql, array $parametros): ?Prescripcion
    {
        try {
            $consulta = $this->pdo->prepare($sql);
            $consulta->execute($parametros);
            $fila = $consulta->fetch(\PDO::FETCH_ASSOC);
            if ($fila === false) {
                return null;
            }
            $items = $this->pdo->prepare('SELECT medication_code, dose, unit, frequency_hours, route, duration_days
                FROM prescription_items WHERE prescription_id = :id ORDER BY line_no');
            $items->execute(['id' => $fila['prescription_id']]);

            return $this->hidratar($fila, $items->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo leer la prescripción.', 0, $e);
        }
    }

    /**
     * @param array<string, mixed>       $f
     * @param list<array<string, mixed>> $items
     */
    private function hidratar(array $f, array $items): Prescripcion
    {
        $hallazgos = array_map(static fn (array $h): Hallazgo => Hallazgo::desdeArreglo($h),
            json_decode((string) $f['allergy_findings'], true, 8, \JSON_THROW_ON_ERROR));

        return new Prescripcion(
            (int) $f['prescription_id'], $f['hospital'], $f['local_patient_id'], $f['prescriber'], $f['idempotency_key'], trim($f['content_hash']),
            array_map(static fn (array $i): array => [
                'medicamento' => $i['medication_code'], 'dosis' => (float) $i['dose'], 'unidad' => $i['unit'],
                'frecuencia_horas' => (int) $i['frequency_hours'], 'via' => $i['route'], 'dias' => (int) $i['duration_days'],
            ], $items),
            new ResultadoVerificacion($f['allergy_check_result'], $hallazgos),
            json_decode((string) $f['allergens_checked'], true, 4, \JSON_THROW_ON_ERROR),
            $f['override_justification'],
            (bool) $f['no_known_allergies_confirmed'],
            new \DateTimeImmutable($f['issued_at']),
            $f['status'],
        );
    }
}

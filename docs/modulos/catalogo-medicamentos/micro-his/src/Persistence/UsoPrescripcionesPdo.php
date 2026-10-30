<?php
declare(strict_types=1);

namespace Catalogo\Persistence;

use Catalogo\Domain\Errores\ErrorPersistencia;
use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Puertos\ConsultaUsoPrescripciones;

/**
 * Adaptador del puerto RN-02. En el Micro-HIS lee la tabla de SIMULACIÓN referencias_prescripcion;
 * en el HIS leería lo que publique prescripciones-alergias (vista o API, pendiente de acordar).
 */
final class UsoPrescripcionesPdo implements ConsultaUsoPrescripciones
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function fueUsado(string $hospitalId, CodigoMedicamento $codigo): bool
    {
        try {
            $st = $this->pdo->prepare('SELECT 1 FROM referencias_prescripcion WHERE hospital_id = :h AND codigo_medicamento = :c LIMIT 1');
            $st->execute(['h' => $hospitalId, 'c' => $codigo->valor]);
            return $st->fetchColumn() !== false;
        } catch (\PDOException $e) {
            throw new ErrorPersistencia($e);
        }
    }
}

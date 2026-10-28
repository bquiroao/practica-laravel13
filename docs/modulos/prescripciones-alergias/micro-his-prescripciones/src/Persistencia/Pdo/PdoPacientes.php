<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia\Pdo;

use Prescripciones\Aplicacion\Puertos\Pacientes;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;

final class PdoPacientes implements Pacientes
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function existeEnHospital(string $paciente, string $hospital): bool
    {
        try {
            $consulta = $this->pdo->prepare('SELECT 1 FROM local_patients WHERE local_patient_id = :paciente AND hospital = :hospital');
            $consulta->execute(['paciente' => $paciente, 'hospital' => $hospital]);

            return $consulta->fetchColumn() !== false;
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo verificar el paciente.', 0, $e);
        }
    }
}

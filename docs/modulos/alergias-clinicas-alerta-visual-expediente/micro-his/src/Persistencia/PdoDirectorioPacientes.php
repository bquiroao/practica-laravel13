<?php
declare(strict_types=1);

namespace Alergias\Persistencia;

use Alergias\Dominio\DirectorioPacientes;
use Alergias\Dominio\ErrorPersistencia;
use Alergias\Dominio\PacienteRef;
use PDO;
use PDOException;

/** Solo lee local_patients; nunca escribe en la tabla del módulo pacientes. */
final class PdoDirectorioPacientes implements DirectorioPacientes
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function porCodigo(string $codigo): ?PacienteRef
    {
        try {
            $sql = $this->pdo->prepare('SELECT local_patient_id, code, status FROM local_patients WHERE code = :codigo');
            $sql->execute(['codigo' => $codigo]);
            $fila = $sql->fetch();
        } catch (PDOException $e) {
            throw new ErrorPersistencia('No se pudo leer el paciente.', 0, $e);
        }
        return $fila ? new PacienteRef($fila['local_patient_id'], $fila['code'], $fila['status']) : null;
    }
}

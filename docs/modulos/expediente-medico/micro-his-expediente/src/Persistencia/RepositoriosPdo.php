<?php
declare(strict_types=1);

namespace MicroHis\Expediente\Persistencia;

use MicroHis\Expediente\Aplicacion\{ErrorPersistencia, IndiceCentral, RepositorioExpedientes};
use MicroHis\Expediente\Dominio\Paciente;
use PDO, PDOException, PDOStatement;

/** Base común de los adaptadores: solo sentencias preparadas; PDOException -> ErrorPersistencia. */
abstract class AdaptadorPdo
{
    public function __construct(protected PDO $db) { $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    protected function ejecutar(string $sql, array $parametros): PDOStatement
    {
        try { $q = $this->db->prepare($sql); $q->execute($parametros); return $q; }
        catch (PDOException $e) { throw new ErrorPersistencia('No se pudo acceder al expediente.', 0, $e); }
    }
}

/** Base HOSPITAL: pacientes, asignaciones, expedientes y auditoría. */
final class HospitalPdo extends AdaptadorPdo implements RepositorioExpedientes
{
    public function paciente(string $codigo): ?Paciente
    {
        $f = $this->ejecutar('SELECT p.code, p.global_id, p.status, p.mpi_link_status, r.record_number FROM local_patients p
                              LEFT JOIN medical_records r ON r.patient_code = p.code WHERE p.code = ?', [$codigo])->fetch(PDO::FETCH_ASSOC);
        return $f ? new Paciente($f['code'], $f['global_id'], $f['status'], $f['mpi_link_status'] === 'linked', $f['record_number']) : null;
    }
    public function asignacionActiva(string $p, string $m): bool { return (bool) $this->ejecutar("SELECT 1 FROM care_assignments WHERE patient_code = ? AND doctor = ? AND status = 'active'", [$p, $m])->fetchColumn(); }
    public function abrirExpediente(string $paciente, string $numero): void { $this->ejecutar('INSERT INTO medical_records (patient_code, record_number) VALUES (?, ?)', [$paciente, $numero]); }
    public function registrar(string $m, string $op, string $p, string $res): void { $this->ejecutar('INSERT INTO audit_events (actor, action, patient_code, outcome) VALUES (?, ?, ?, ?)', [$m, $op, $p, $res]); }
}

/** Base CENTRAL: consentimientos e índice de expedientes por hospital (MPI). */
final class CentralPdo extends AdaptadorPdo implements IndiceCentral
{
    public function consentimiento(string $idGlobal, string $hospital): ?array
    {
        $f = $this->ejecutar("SELECT valid_from, valid_to FROM consents WHERE global_id = ? AND hospital_id = ? AND status = 'active'", [$idGlobal, $hospital])->fetch(PDO::FETCH_ASSOC);
        return $f ? ['desde' => (int) $f['valid_from'], 'hasta' => $f['valid_to'] === null ? null : (int) $f['valid_to']] : null;
    }
    public function expedientesExternos(string $idGlobal, string $hospital): array
    {
        return $this->ejecutar('SELECT hospital_id AS hospital, record_number AS expediente FROM mpi_records WHERE global_id = ? AND hospital_id <> ? ORDER BY hospital_id',
                               [$idGlobal, $hospital])->fetchAll(PDO::FETCH_ASSOC);
    }
}

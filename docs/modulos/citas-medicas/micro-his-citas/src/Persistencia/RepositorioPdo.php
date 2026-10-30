<?php
declare(strict_types=1);

namespace MicroHis\Citas\Persistencia;

use MicroHis\Citas\Aplicacion\{ErrorPersistencia, HorarioTomado, RepositorioCitas};
use MicroHis\Citas\Dominio\Horario;
use PDO, PDOException, PDOStatement;

/** Adaptador PDO del puerto RepositorioCitas. Solo sentencias preparadas; PDOException -> ErrorPersistencia. */
final class RepositorioPdo implements RepositorioCitas
{
    public function __construct(private PDO $db) { $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }

    private function ejecutar(string $sql, array $parametros): PDOStatement
    {
        try { $q = $this->db->prepare($sql); $q->execute($parametros); return $q; }
        catch (PDOException $e) { throw new ErrorPersistencia('No se pudo acceder a la agenda.', 0, $e); }
    }

    public function medicoVigente(string $medico, string $especialidad): bool { return (bool) $this->ejecutar("SELECT 1 FROM doctor_specialties WHERE doctor = ? AND specialty = ? AND status = 'active'", [$medico, $especialidad])->fetchColumn(); }
    public function cruce(string $medico, string $paciente, int $inicio, int $fin): ?string
    {
        $r = $this->ejecutar("SELECT doctor = ? AS del_medico FROM appointments WHERE status IN ('scheduled', 'confirmed')
                              AND starts_at < ? AND ends_at > ? AND (doctor = ? OR patient = ?) ORDER BY del_medico DESC LIMIT 1",
                             [$medico, $fin, $inicio, $medico, $paciente])->fetchColumn();
        return $r === false ? null : ($r ? 'medico' : 'paciente');
    }
    public function guardar(string $medico, string $paciente, string $especialidad, Horario $h, string $motivo): int
    {
        // La condición de cruce va dentro del INSERT: en SQLite la sentencia es atómica (en PostgreSQL lo garantiza la exclusión gist).
        $n = $this->ejecutar("INSERT INTO appointments (doctor, patient, specialty, starts_at, ends_at, status, reason)
                              SELECT ?, ?, ?, ?, ?, 'scheduled', ? WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE status IN ('scheduled', 'confirmed')
                              AND starts_at < ? AND ends_at > ? AND (doctor = ? OR patient = ?))",
                             [$medico, $paciente, $especialidad, $h->inicio, $h->fin, $motivo, $h->fin, $h->inicio, $medico, $paciente])->rowCount();
        if ($n === 0) throw new HorarioTomado('Otra solicitud tomó el horario.');
        return (int) $this->db->lastInsertId();
    }
    public function estado(int $id): ?string { return $this->ejecutar('SELECT status FROM appointments WHERE id = ?', [$id])->fetchColumn() ?: null; }

    public function cambiarEstado(int $id, string $de, string $a, ?string $motivo): bool
    {
        $this->db->beginTransaction();
        try {
            $n = $this->ejecutar('UPDATE appointments SET status = ? WHERE id = ? AND status = ?', [$a, $id, $de])->rowCount();
            if ($n === 1 && $motivo !== null) $this->ejecutar('INSERT INTO appointment_cancellations (appointment_id, reason) VALUES (?, ?)', [$id, $motivo]);
            $this->db->commit();
            return $n === 1;
        } catch (ErrorPersistencia $e) { $this->db->rollBack(); throw $e; }
    }
}

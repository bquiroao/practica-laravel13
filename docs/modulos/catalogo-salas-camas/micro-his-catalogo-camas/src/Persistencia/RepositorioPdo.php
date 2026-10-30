<?php
declare(strict_types=1);

namespace MicroHis\Catalogo\Persistencia;

use MicroHis\Catalogo\Aplicacion\{ErrorPersistencia, RepositorioCatalogo};
use PDO;
use PDOException;
use PDOStatement;

/** Adaptador PDO del puerto RepositorioCatalogo. Solo sentencias preparadas; PDOException -> ErrorPersistencia. */
final class RepositorioPdo implements RepositorioCatalogo
{
    public function __construct(private PDO $db) { $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }

    private function ejecutar(string $sql, array $parametros): PDOStatement
    {
        try { $q = $this->db->prepare($sql); $q->execute($parametros); return $q; }
        catch (PDOException $e) { throw new ErrorPersistencia('No se pudo acceder al catálogo.', 0, $e); }
    }

    public function salaActiva(string $sala): ?bool { $e = $this->ejecutar('SELECT status FROM wards WHERE code = ?', [$sala])->fetchColumn(); return $e === false ? null : $e === 'active'; }
    public function guardarSala(string $sala, string $nombre, ?int $capacidad = null): void { $this->ejecutar("INSERT INTO wards (code, name, status, capacity) VALUES (?, ?, 'active', ?)", [$sala, $nombre, $capacidad]); }
    public function capacidad(string $sala): ?int { $c = $this->ejecutar('SELECT capacity FROM wards WHERE code = ?', [$sala])->fetchColumn(); return is_numeric($c) ? (int) $c : null; }
    public function camasEnUso(string $sala): int { return (int) $this->ejecutar("SELECT COUNT(*) FROM beds b JOIN wards w ON w.ward_id = b.ward_id WHERE w.code = ? AND b.status <> 'inactive'", [$sala])->fetchColumn(); }
    public function estadoCama(string $sala, string $cama): ?string { return $this->ejecutar('SELECT b.status FROM beds b JOIN wards w ON w.ward_id = b.ward_id WHERE w.code = ? AND b.code = ?', [$sala, $cama])->fetchColumn() ?: null; }

    /** El INSERT vuelve a contar en la misma sentencia: en SQLite dos altas simultáneas no pasan juntas el tope. */
    public function guardarCama(string $sala, string $cama): bool
    {
        return $this->ejecutar("INSERT INTO beds (ward_id, code, status) SELECT w.ward_id, ?, 'available' FROM wards w WHERE w.code = ?
            AND (w.capacity IS NULL OR w.capacity > (SELECT COUNT(*) FROM beds b WHERE b.ward_id = w.ward_id AND b.status <> 'inactive'))", [$cama, $sala])->rowCount() === 1;
    }

    public function cambiarEstado(string $sala, string $cama, string $de, string $a, string $motivo): bool
    {
        $this->db->beginTransaction();
        try {
            $n = $this->ejecutar('UPDATE beds SET status = ? WHERE code = ? AND status = ? AND ward_id = (SELECT ward_id FROM wards WHERE code = ?)', [$a, $cama, $de, $sala])->rowCount();
            if ($n === 1) $this->ejecutar('INSERT INTO bed_status_history (ward_code, bed_code, from_status, to_status, reason) VALUES (?, ?, ?, ?, ?)', [$sala, $cama, $de, $a, $motivo]);
            $this->db->commit();
            return $n === 1;
        } catch (ErrorPersistencia $e) { $this->db->rollBack(); throw $e; }
    }
}

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
    public function guardarSala(string $sala, string $nombre): void { $this->ejecutar("INSERT INTO wards (code, name, status) VALUES (?, ?, 'active')", [$sala, $nombre]); }
    public function estadoCama(string $sala, string $cama): ?string { return $this->ejecutar('SELECT b.status FROM beds b JOIN wards w ON w.ward_id = b.ward_id WHERE w.code = ? AND b.code = ?', [$sala, $cama])->fetchColumn() ?: null; }
    public function guardarCama(string $sala, string $cama): void { $this->ejecutar("INSERT INTO beds (ward_id, code, status) SELECT ward_id, ?, 'available' FROM wards WHERE code = ?", [$cama, $sala]); }

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

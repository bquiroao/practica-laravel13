<?php
declare(strict_types=1);

namespace MicroHis\ContratosApi\Persistencia;

use MicroHis\ContratosApi\Aplicacion\{ErrorPersistencia, RepositorioDocumentos};
use MicroHis\ContratosApi\Dominio\VersionNueva;
use PDO, PDOException, PDOStatement;

/** Adaptador PDO de la interfaz Repository (los tres puertos). Solo sentencias preparadas; PDOException -> ErrorPersistencia. */
final class RepositorioPdo implements RepositorioDocumentos
{
    public function __construct(private PDO $db) { $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }

    private function ejecutar(string $sql, array $parametros): PDOStatement
    {
        try { $q = $this->db->prepare($sql); $q->execute($parametros); return $q; }
        catch (PDOException $e) { throw new ErrorPersistencia('No se pudo acceder al centro de documentación.', 0, $e); }
    }

    public function versiones(string $slug): array { return $this->ejecutar("SELECT version FROM doc_versions WHERE slug = ? AND status <> 'rejected'", [$slug])->fetchAll(PDO::FETCH_COLUMN); }
    public function guardarEnRevision(VersionNueva $v, string $autor): int
    {
        $this->db->beginTransaction();   // la versión y su audiencia: o se guarda todo o nada
        try {
            $this->ejecutar("INSERT INTO doc_versions (slug, doc_type, version, author, status) VALUES (?, ?, ?, ?, 'in_review')", [$v->slug, $v->tipo, $v->version, $autor]);
            $id = (int) $this->db->lastInsertId();
            foreach ($v->audiencia as $rol) $this->ejecutar('INSERT INTO doc_audiences (version_id, role_code) VALUES (?, ?)', [$id, $rol]);
            $this->db->commit();
            return $id;
        } catch (ErrorPersistencia $e) { $this->db->rollBack(); throw $e; }
    }
    public function datosRevision(int $id): ?array { return $this->ejecutar('SELECT author AS autor, status AS estado FROM doc_versions WHERE id = ?', [$id])->fetch(PDO::FETCH_ASSOC) ?: null; }
    public function cambiarEstado(int $id, string $de, string $a, string $revisor, ?string $motivo): bool
    {
        return $this->ejecutar('UPDATE doc_versions SET status = ?, reviewed_by = ?, reason = COALESCE(?, reason) WHERE id = ? AND status = ?',
                               [$a, $revisor, $motivo, $id, $de])->rowCount() === 1;
    }
    public function consultables(string $slug): array
    {
        $r = [];
        foreach ($this->ejecutar("SELECT v.version, v.doc_type, v.status, a.role_code FROM doc_versions v JOIN doc_audiences a ON a.version_id = v.id
                                  WHERE v.slug = ? AND v.status IN ('published', 'obsolete')", [$slug])->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $r[$f['version']] ??= ['version' => $f['version'], 'tipo' => $f['doc_type'], 'estado' => $f['status'], 'audiencia' => []];
            $r[$f['version']]['audiencia'][] = $f['role_code'];
        }
        return array_values($r);
    }
}

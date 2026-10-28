<?php
declare(strict_types=1);

// Persistencia: adaptador PDO de los dos puertos (Puertos.php). Lectura de vistas de solo lectura y registro de exportaciones, siempre con sentencias preparadas.
namespace Reportes\Persistencia;

use Reportes\Aplicacion\{ErrorPersistencia, FuenteReportes, RepositorioExportaciones};
use Reportes\Dominio\Filtros;

final class ReportesPdo implements RepositorioExportaciones, FuenteReportes {
    public function __construct(private \PDO $pdo) { $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); }

    /** Filas del hospital, del rango y registradas hasta el corte. Vista y columnas vienen del catálogo; los valores van como parámetros. */
    public function filas(string $vista, string $hospitalId, Filtros $f, string $corte): array {
        $nombres = array_merge([$vista], array_keys($f->extras));
        if (preg_grep('/^[a-z_]+$/', $nombres) !== $nombres) { throw new \InvalidArgumentException('Identificador no permitido'); }
        $sql = "SELECT * FROM $vista WHERE hospital_id = ? AND fecha BETWEEN ? AND ? AND registrado_en <= ?"
             . implode('', array_map(fn ($c) => " AND $c = ?", array_keys($f->extras)));
        return $this->ejecutar($sql, array_merge([$hospitalId, $f->desde, $f->hasta, $corte], array_values($f->extras)))->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function guardarExportacion(array $e): string {
        $id = 'exp-' . bin2hex(random_bytes(6));
        $this->ejecutar('INSERT INTO exportaciones_reporte (id, codigo, filtros, formato, corte, filas, hash, usuario_id, hospital_id, datos_personales) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $e['codigo'], json_encode($e['filtros']), $e['formato'], $e['corte'], $e['filas'], $e['hash'], $e['usuario_id'], $e['hospital_id'], (int) $e['datos_personales']]);
        return $id;
    }

    public function exportacion(string $id, string $hospitalId): ?array {
        $fila = $this->ejecutar('SELECT * FROM exportaciones_reporte WHERE id = ? AND hospital_id = ?', [$id, $hospitalId])->fetch(\PDO::FETCH_ASSOC);
        return $fila ? ['filtros' => json_decode($fila['filtros'], true)] + $fila : null;
    }

    private function ejecutar(string $sql, array $parametros): \PDOStatement {
        try { $consulta = $this->pdo->prepare($sql); $consulta->execute($parametros); return $consulta; }
        catch (\PDOException $e) { throw new ErrorPersistencia('No se pudo acceder a la base', 0, $e); }
    }
}

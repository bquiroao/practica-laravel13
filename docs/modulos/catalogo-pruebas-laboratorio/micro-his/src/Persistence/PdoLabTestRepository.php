<?php
declare(strict_types=1);

namespace Lab\Persistence;

use DateTimeImmutable;
use Lab\Domain\ErrorPersistencia;
use Lab\Domain\LabTest;
use Lab\Domain\LabTestRepository;
use Lab\Domain\RequisitoMuestra;
use Lab\Domain\Vigencia;
use PDO;
use PDOException;

/** Adaptador PostgreSQL: siempre prepare() + execute(). */
final class PdoLabTestRepository implements LabTestRepository
{
    private const COLUMNAS = 'id, tenant_id, code, version, name, section, turnaround_hours, specimen_type,
        container, min_volume_ml, fasting_hours, preparation_notes, storage_condition, stability_hours,
        valid_from, valid_to, created_by, created_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ultimaVersion(string $tenantId, string $codigo): ?LabTest
    {
        $filas = $this->consultar(
            'SELECT ' . self::COLUMNAS . ' FROM lab_test WHERE tenant_id = :t AND code = :c ORDER BY version DESC LIMIT 1',
            [':t' => $tenantId, ':c' => $codigo],
        );
        return $filas ? self::aEntidad($filas[0]) : null;
    }

    public function guardarNuevaVersion(LabTest $nueva, ?LabTest $anteriorCerrada): void
    {
        try {
            $this->pdo->beginTransaction();
            if ($anteriorCerrada !== null) {
                $this->pdo->prepare('UPDATE lab_test SET valid_to = :hasta WHERE id = :id')->execute([
                    ':hasta' => $anteriorCerrada->vigencia->hasta?->format('Y-m-d'),
                    ':id' => $anteriorCerrada->id,
                ]);
            }
            $r = $nueva->requisito;
            $this->pdo->prepare('INSERT INTO lab_test (' . self::COLUMNAS . ') VALUES (:id, :t, :c, :v, :n, :s, :tr,
                :tm, :co, :vol, :ay, :pr, :cons, :est, :vd, :vh, :cp, :ce)')->execute([
                ':id' => $nueva->id, ':t' => $nueva->tenantId, ':c' => $nueva->codigo, ':v' => $nueva->version,
                ':n' => $nueva->nombre, ':s' => $nueva->seccion, ':tr' => $nueva->tiempoRespuestaHoras,
                ':tm' => $r->tipoMuestra, ':co' => $r->contenedor, ':vol' => $r->volumenMinimoMl,
                ':ay' => $r->horasAyuno, ':pr' => $r->instruccionesPreparacion, ':cons' => $r->conservacion,
                ':est' => $r->estabilidadHoras, ':vd' => $nueva->vigencia->desde->format('Y-m-d'),
                ':vh' => $nueva->vigencia->hasta?->format('Y-m-d'), ':cp' => $nueva->creadoPor,
                ':ce' => $nueva->creadoEn->format(DATE_ATOM),
            ]);
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new ErrorPersistencia('No se pudo guardar la prueba; no se guardó ningún cambio.', 0, $e);
        }
    }

    public function buscarVigentes(string $tenantId, DateTimeImmutable $fecha, string $texto = '', ?string $seccion = null, ?string $tipoMuestra = null): array
    {
        $sql = 'SELECT ' . self::COLUMNAS . ' FROM lab_test
            WHERE tenant_id = :t AND valid_from <= :f1 AND (valid_to IS NULL OR valid_to >= :f2)';
        $p = [':t' => $tenantId, ':f1' => $fecha->format('Y-m-d'), ':f2' => $fecha->format('Y-m-d')];
        if ($texto !== '') {
            $sql .= ' AND (name ILIKE :q1 OR code ILIKE :q2)';
            $p[':q1'] = $p[':q2'] = '%' . $texto . '%';
        }
        if ($seccion !== null) {
            $sql .= ' AND section = :s';
            $p[':s'] = $seccion;
        }
        if ($tipoMuestra !== null) {
            $sql .= ' AND specimen_type = :tm';
            $p[':tm'] = $tipoMuestra;
        }
        return array_map([self::class, 'aEntidad'], $this->consultar($sql . ' ORDER BY code', $p));
    }

    public function buscarVigentePorCodigo(string $tenantId, string $codigo, DateTimeImmutable $fecha): ?LabTest
    {
        $filas = $this->consultar(
            'SELECT ' . self::COLUMNAS . ' FROM lab_test WHERE tenant_id = :t AND code = :c
             AND valid_from <= :f1 AND (valid_to IS NULL OR valid_to >= :f2)',
            [':t' => $tenantId, ':c' => $codigo, ':f1' => $fecha->format('Y-m-d'), ':f2' => $fecha->format('Y-m-d')],
        );
        return $filas ? self::aEntidad($filas[0]) : null;
    }

    private function consultar(string $sql, array $parametros): array
    {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($parametros);
            return $st->fetchAll();
        } catch (PDOException $e) {
            throw new ErrorPersistencia('No se pudo consultar el catálogo.', 0, $e);
        }
    }

    private static function aEntidad(array $f): LabTest
    {
        return new LabTest(
            $f['id'], $f['tenant_id'], $f['code'], (int) $f['version'], $f['name'], $f['section'],
            (int) $f['turnaround_hours'],
            new RequisitoMuestra($f['specimen_type'], $f['container'], (float) $f['min_volume_ml'],
                (int) $f['fasting_hours'], $f['preparation_notes'], $f['storage_condition'], (int) $f['stability_hours']),
            Vigencia::reconstruir($f['valid_from'], $f['valid_to']),
            $f['created_by'], new DateTimeImmutable($f['created_at']),
        );
    }
}

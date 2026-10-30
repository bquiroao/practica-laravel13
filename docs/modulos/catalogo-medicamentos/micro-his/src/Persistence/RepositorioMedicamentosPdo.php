<?php
declare(strict_types=1);

namespace Catalogo\Persistence;

use Catalogo\Domain\Errores\ErrorPersistencia;
use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Modelo\ClaveEquivalencia;
use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\Concentracion;
use Catalogo\Domain\Modelo\CriterioBusqueda;
use Catalogo\Domain\Modelo\FichaMedicamento;
use Catalogo\Domain\Modelo\FormaFarmaceutica;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Domain\Modelo\ViaAdministracion;
use Catalogo\Domain\Modelo\Vigencia;
use Catalogo\Domain\Repositorio\RepositorioMedicamentos;

/**
 * Adaptador PDO pgsql de la interfaz Repository. Siempre prepare() + execute(); en pgsql no se
 * repite un parámetro con nombre en la misma consulta (por eso :f1/:f2, :t1/:t2/:t3).
 * Traduce los errores de la base: restricciones -> ErrorReglaNegocio (409), resto -> ErrorPersistencia.
 */
final class RepositorioMedicamentosPdo implements RepositorioMedicamentos
{
    private const COLUMNAS = 'id, hospital_id, codigo, version, nombre, principio_activo, concentracion_valor,
        concentracion_unidad, forma_farmaceutica, via_administracion, presentacion, registro_sanitario,
        vigente_desde, vigente_hasta, motivo_cierre, creado_por';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function buscarVigentes(string $hospitalId, CriterioBusqueda $criterio): array
    {
        $sql = 'SELECT ' . self::COLUMNAS . ' FROM medicamentos
            WHERE hospital_id = :h AND vigente_desde <= :f1 AND (vigente_hasta IS NULL OR vigente_hasta > :f2)';
        $p = ['h' => $hospitalId, 'f1' => $criterio->fecha->format('Y-m-d'), 'f2' => $criterio->fecha->format('Y-m-d')];
        if ($criterio->texto !== null) {
            // ESCAPE '!' y no barra invertida: el analizador de PDO la toma como comilla escapada y pierde :t2.
            $sql .= " AND (lower(nombre) LIKE :t1 ESCAPE '!' OR lower(principio_activo) LIKE :t2 ESCAPE '!' OR lower(codigo) LIKE :t3 ESCAPE '!')";
            $patron = '%' . strtr($criterio->texto, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $p += ['t1' => $patron, 't2' => $patron, 't3' => $patron];
        }
        if ($criterio->forma !== null) {
            $sql .= ' AND forma_farmaceutica = :forma';
            $p['forma'] = $criterio->forma->value;
        }
        // COLLATE "C": mismo orden que strcmp() del adaptador InMemory, sin depender del locale.
        $sql .= ' ORDER BY nombre COLLATE "C", codigo COLLATE "C" LIMIT ' . $criterio->limite;
        return array_map($this->aMedicamento(...), $this->consultar($sql, $p));
    }

    public function vigentePorCodigo(string $hospitalId, CodigoMedicamento $codigo, \DateTimeImmutable $fecha): ?Medicamento
    {
        $filas = $this->consultar('SELECT ' . self::COLUMNAS . ' FROM medicamentos
            WHERE hospital_id = :h AND codigo = :c AND vigente_desde <= :f1 AND (vigente_hasta IS NULL OR vigente_hasta > :f2)
            LIMIT 1', ['h' => $hospitalId, 'c' => $codigo->valor, 'f1' => $fecha->format('Y-m-d'), 'f2' => $fecha->format('Y-m-d')]);
        return $filas === [] ? null : $this->aMedicamento($filas[0]);
    }

    public function historial(string $hospitalId, CodigoMedicamento $codigo): array
    {
        $filas = $this->consultar('SELECT ' . self::COLUMNAS . ' FROM medicamentos WHERE hospital_id = :h AND codigo = :c ORDER BY version',
            ['h' => $hospitalId, 'c' => $codigo->valor]);
        return array_map($this->aMedicamento(...), $filas);
    }

    public function equivalenteVigente(string $hospitalId, ClaveEquivalencia $clave, Vigencia $vigencia, ?CodigoMedicamento $excluir = null): ?CodigoMedicamento
    {
        $sql = "SELECT codigo FROM medicamentos WHERE hospital_id = :h AND clave_equivalencia = :clave
            AND daterange(vigente_desde, vigente_hasta, '[)') && daterange(CAST(:desde AS date), CAST(:hasta AS date), '[)')";
        $p = ['h' => $hospitalId, 'clave' => $clave->valor, 'desde' => $vigencia->desde->format('Y-m-d'), 'hasta' => $vigencia->hasta?->format('Y-m-d')];
        if ($excluir !== null) {
            $sql .= ' AND codigo <> :excluir';
            $p['excluir'] = $excluir->valor;
        }
        $filas = $this->consultar($sql . ' ORDER BY codigo LIMIT 1', $p);
        return $filas === [] ? null : CodigoMedicamento::desdeTexto($filas[0]['codigo']);
    }

    public function siguienteCodigo(string $hospitalId): CodigoMedicamento
    {
        $filas = $this->consultar('SELECT max(codigo) AS ultimo FROM medicamentos WHERE hospital_id = :h', ['h' => $hospitalId]);
        $ultimo = $filas[0]['ultimo'] ?? null;
        return CodigoMedicamento::desdeNumero($ultimo === null ? 1 : CodigoMedicamento::desdeTexto($ultimo)->numero() + 1);
    }

    public function agregar(Medicamento $nuevo): void
    {
        $this->enTransaccion(fn () => $this->insertar($nuevo));
    }

    public function reemplazarVigente(Medicamento $anteriorCerrada, Medicamento $nueva): void
    {
        // Primero se cierra la anterior: así la restricción EXCLUDE no ve dos versiones abiertas.
        $this->enTransaccion(function () use ($anteriorCerrada, $nueva): void {
            $this->actualizarCierre($anteriorCerrada);
            $this->insertar($nueva);
        });
    }

    public function cerrar(Medicamento $cerrada): void
    {
        $this->enTransaccion(fn () => $this->actualizarCierre($cerrada));
    }

    public function eliminar(string $hospitalId, CodigoMedicamento $codigo): void
    {
        $this->enTransaccion(function () use ($hospitalId, $codigo): void {
            $st = $this->pdo->prepare('DELETE FROM medicamentos WHERE hospital_id = :h AND codigo = :c');
            $st->execute(['h' => $hospitalId, 'c' => $codigo->valor]);
        });
    }

    private function insertar(Medicamento $m): void
    {
        $f = $m->ficha;
        $st = $this->pdo->prepare('INSERT INTO medicamentos (id, hospital_id, codigo, version, nombre, principio_activo,
            clave_equivalencia, concentracion_valor, concentracion_unidad, forma_farmaceutica, via_administracion,
            presentacion, registro_sanitario, vigente_desde, vigente_hasta, motivo_cierre, creado_por)
            VALUES (:id, :h, :codigo, :version, :nombre, :principio, :clave, :valor, :unidad, :forma, :via,
            :presentacion, :registro, :desde, :hasta, :motivo, :usuario)');
        $st->execute([
            'id' => $m->id, 'h' => $m->hospitalId, 'codigo' => $m->codigo->valor, 'version' => $m->version,
            'nombre' => $f->nombre, 'principio' => $f->principioActivo, 'clave' => $m->clave()->valor,
            'valor' => $f->concentracion->valor, 'unidad' => $f->concentracion->unidad->value,
            'forma' => $f->forma->value, 'via' => $f->via->value, 'presentacion' => $f->presentacion,
            'registro' => $f->registroSanitario, 'desde' => $m->vigencia->desde->format('Y-m-d'),
            'hasta' => $m->vigencia->hasta?->format('Y-m-d'), 'motivo' => $m->motivoCierre, 'usuario' => $m->creadoPor,
        ]);
    }

    private function actualizarCierre(Medicamento $cerrada): void
    {
        $st = $this->pdo->prepare('UPDATE medicamentos SET vigente_hasta = :hasta, motivo_cierre = :motivo, actualizado_en = now()
            WHERE id = :id AND hospital_id = :h AND vigente_hasta IS NULL');
        $st->execute(['hasta' => $cerrada->vigencia->hasta?->format('Y-m-d'), 'motivo' => $cerrada->motivoCierre, 'id' => $cerrada->id, 'h' => $cerrada->hospitalId]);
        if ($st->rowCount() !== 1) {
            throw new ErrorReglaNegocio('CONFLICTO_CONCURRENTE', 'Otra persona cambió este medicamento. Vuelva a cargarlo e intente de nuevo.');
        }
    }

    private function enTransaccion(callable $trabajo): void
    {
        try {
            $this->pdo->beginTransaction();
            $trabajo();
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e instanceof \PDOException ? $this->traducir($e) : $e;
        }
    }

    /** @return list<array<string, mixed>> */
    private function consultar(string $sql, array $parametros): array
    {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($parametros);
            return $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw $this->traducir($e);
        }
    }

    private function traducir(\PDOException $e): \Throwable
    {
        $estado = (string) ($e->errorInfo[0] ?? $e->getCode());
        $detalle = (string) ($e->errorInfo[2] ?? $e->getMessage());
        return match (true) {
            $estado === '23P01' && str_contains($detalle, 'ex_medicamentos_clave_vigencia') => new ErrorReglaNegocio('DUPLICADO', 'Ya existe un medicamento equivalente vigente en esas fechas'),
            $estado === '23P01' => new ErrorReglaNegocio('SOLAPE_VIGENCIA', 'Las vigencias de este código se cruzarían'),
            $estado === '23505' => new ErrorReglaNegocio('CONFLICTO_CONCURRENTE', 'Otra persona registró este código o versión al mismo tiempo. Intente de nuevo.'),
            str_starts_with($estado, '23') => new ErrorReglaNegocio('INTEGRIDAD', 'La base de datos rechazó el cambio por una regla de integridad'),
            default => new ErrorPersistencia($e),
        };
    }

    /** @param array<string, mixed> $r */
    private function aMedicamento(array $r): Medicamento
    {
        $ficha = new FichaMedicamento(
            $r['nombre'],
            $r['principio_activo'],
            Concentracion::reconstituir((string) $r['concentracion_valor'], $r['concentracion_unidad']),
            FormaFarmaceutica::from($r['forma_farmaceutica']),
            ViaAdministracion::from($r['via_administracion']),
            $r['presentacion'],
            $r['registro_sanitario'],
        );
        $utc = new \DateTimeZone('UTC');
        $vigencia = new Vigencia(
            new \DateTimeImmutable($r['vigente_desde'] . ' 00:00:00', $utc),
            $r['vigente_hasta'] === null ? null : new \DateTimeImmutable($r['vigente_hasta'] . ' 00:00:00', $utc),
        );
        return new Medicamento($r['id'], $r['hospital_id'], CodigoMedicamento::desdeTexto($r['codigo']), (int) $r['version'], $ficha, $vigencia, $r['creado_por'], $r['motivo_cierre']);
    }
}

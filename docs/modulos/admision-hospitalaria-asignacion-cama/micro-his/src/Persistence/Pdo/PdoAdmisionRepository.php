<?php
declare(strict_types=1);

namespace Admision\Persistence\Pdo;

use Admision\Domain\Excepciones\CamaYaAsignada;
use Admision\Domain\Excepciones\ConflictoConcurrencia;
use Admision\Domain\Excepciones\ErrorPersistencia;
use Admision\Domain\Excepciones\PacienteConAdmisionActiva;
use Admision\Domain\Modelo\Admision;
use Admision\Domain\Modelo\CodigoPaciente;
use Admision\Domain\Modelo\EstadoAdmision;
use Admision\Domain\Modelo\EventoAdmision;
use Admision\Domain\Modelo\OrigenAdmision;
use Admision\Domain\Modelo\Requerimiento;
use Admision\Domain\Modelo\Responsable;
use Admision\Domain\Modelo\Rol;
use Admision\Domain\Modelo\TenantId;
use Admision\Domain\Modelo\TipoCama;
use Admision\Domain\Modelo\TipoEvento;
use Admision\Domain\Repositorio\AdmisionRepository;

/** Adaptador PDO de los datos propios del módulo. Siempre prepare() + execute(). */
final class PdoAdmisionRepository implements AdmisionRepository
{
    public function __construct(private readonly \PDO $pdo, private readonly int $esperaMaximaMs = 2000)
    {
    }

    public function registrar(Admision $a): void
    {
        try {
            $this->pdo->beginTransaction();
            // RNF-08: no esperar sin límite si otra transacción tiene la misma cama.
            $this->ejecutar("SELECT set_config('lock_timeout', :espera, true)", [':espera' => $this->esperaMaximaMs . 'ms']);
            $this->ejecutar('INSERT INTO admision (id, tenant_id, codigo_paciente, servicio_requerido, tipo_cama_requerido,
                    requiere_aislamiento, origen, estado, creada_por, creada_por_rol, solicitud_id, creada_en)
                VALUES (:id, :tenant, :paciente, :servicio, :tipo, :aislamiento, :origen, :estado, :usuario, :rol, :solicitud, :en)', [
                ':id' => $a->id, ':tenant' => $a->tenantId->valor, ':paciente' => $a->codigoPaciente->valor,
                ':servicio' => $a->requerimiento->servicio, ':tipo' => $a->requerimiento->tipo->value,
                ':aislamiento' => $a->requerimiento->requiereAislamiento ? 'true' : 'false', ':origen' => $a->origen->value,
                ':estado' => $a->estado->value, ':usuario' => $a->creadaPor->usuarioId, ':rol' => $a->creadaPor->rol->value,
                ':solicitud' => $a->creadaPor->solicitudId, ':en' => $a->creadaEn->format(\DateTimeInterface::ATOM),
            ]);
            // RN-01: si otra admisión ya tiene la cama, ux_asignacion_cama_activa rechaza esta fila.
            $this->ejecutar('INSERT INTO asignacion_cama (admision_id, tenant_id, cama_id, cama_codigo, asignada_por, asignada_en)
                VALUES (:admision, :tenant, :cama, :codigo, :usuario, :en)', [
                ':admision' => $a->id, ':tenant' => $a->tenantId->valor, ':cama' => $a->camaId, ':codigo' => $a->camaCodigo,
                ':usuario' => $a->creadaPor->usuarioId, ':en' => $a->creadaEn->format(\DateTimeInterface::ATOM),
            ]);
            foreach ($a->eventosPendientes() as $e) {
                $this->ejecutar('INSERT INTO evento_admision (admision_id, tenant_id, tipo, usuario_id, rol, solicitud_id, ocurrido_en, detalle)
                    VALUES (:admision, :tenant, :tipo, :usuario, :rol, :solicitud, :en, CAST(:detalle AS jsonb))', [
                    ':admision' => $e->admisionId, ':tenant' => $e->tenantId->valor, ':tipo' => $e->tipo->value,
                    ':usuario' => $e->responsable->usuarioId, ':rol' => $e->responsable->rol->value,
                    ':solicitud' => $e->responsable->solicitudId, ':en' => $e->ocurridoEn->format(\DateTimeInterface::ATOM),
                    ':detalle' => json_encode($e->detalle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ]);
            }
            $this->pdo->commit();
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack(); // RF-04: todo o nada
            }
            throw $this->traducir($e);
        }
    }

    public function tieneAdmisionActiva(TenantId $tenantId, CodigoPaciente $paciente): bool
    {
        return $this->consultar("SELECT 1 FROM admision WHERE tenant_id = :tenant AND codigo_paciente = :paciente AND estado = 'ACTIVA'", [
            ':tenant' => $tenantId->valor, ':paciente' => $paciente->valor,
        ]) !== [];
    }

    public function camasOcupadas(TenantId $tenantId): array
    {
        $filas = $this->consultar('SELECT cama_id FROM asignacion_cama WHERE tenant_id = :tenant AND liberada_en IS NULL', [':tenant' => $tenantId->valor]);
        return array_column($filas, 'cama_id');
    }

    public function buscarPorId(TenantId $tenantId, string $admisionId): ?Admision
    {
        if (!Uuid::esValido($admisionId)) {
            return null;
        }
        $filas = $this->consultar('SELECT a.*, ac.cama_id, ac.cama_codigo FROM admision a
            JOIN asignacion_cama ac ON ac.admision_id = a.id
            WHERE a.tenant_id = :tenant AND a.id = :id ORDER BY ac.asignada_en DESC LIMIT 1', [
            ':tenant' => $tenantId->valor, ':id' => $admisionId,
        ]);
        if ($filas === []) {
            return null;
        }
        $f = $filas[0];
        return Admision::reconstituir(
            $f['id'], TenantId::desde($f['tenant_id']), CodigoPaciente::desde($f['codigo_paciente']),
            new Requerimiento($f['servicio_requerido'], TipoCama::from($f['tipo_cama_requerido']), (bool) $f['requiere_aislamiento']),
            OrigenAdmision::from($f['origen']), EstadoAdmision::from($f['estado']), $f['cama_id'], $f['cama_codigo'],
            new Responsable($f['creada_por'], Rol::from($f['creada_por_rol']), $f['solicitud_id']),
            new \DateTimeImmutable($f['creada_en']),
        );
    }

    public function eventos(TenantId $tenantId, string $admisionId): array
    {
        if (!Uuid::esValido($admisionId)) {
            return [];
        }
        $filas = $this->consultar('SELECT * FROM evento_admision WHERE tenant_id = :tenant AND admision_id = :id ORDER BY orden', [
            ':tenant' => $tenantId->valor, ':id' => $admisionId,
        ]);
        return array_map(fn (array $f) => new EventoAdmision(
            $f['admision_id'], TenantId::desde($f['tenant_id']), TipoEvento::from($f['tipo']),
            new Responsable($f['usuario_id'], Rol::from($f['rol']), $f['solicitud_id']),
            new \DateTimeImmutable($f['ocurrido_en']), json_decode($f['detalle'], true, 512, JSON_THROW_ON_ERROR),
        ), $filas);
    }

    private function ejecutar(string $sql, array $parametros): \PDOStatement
    {
        $sentencia = $this->pdo->prepare($sql);
        $sentencia->execute($parametros);
        return $sentencia;
    }

    private function consultar(string $sql, array $parametros): array
    {
        try {
            return $this->ejecutar($sql, $parametros)->fetchAll();
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo consultar las admisiones.', 0, $e);
        }
    }

    /** Traduce el SQLSTATE de PostgreSQL a errores del dominio. */
    private function traducir(\PDOException $e): \Throwable
    {
        $estado = (string) ($e->errorInfo[0] ?? $e->getCode());
        return match (true) {
            $estado === '23505' && str_contains($e->getMessage(), 'ux_asignacion_cama_activa') => new CamaYaAsignada(),
            $estado === '23505' && str_contains($e->getMessage(), 'ux_admision_paciente_activa') => new PacienteConAdmisionActiva(),
            $estado === '55P03' => new ConflictoConcurrencia(),
            default => new ErrorPersistencia('No se pudo registrar la admisión. No se guardó ningún dato.', 0, $e),
        };
    }
}

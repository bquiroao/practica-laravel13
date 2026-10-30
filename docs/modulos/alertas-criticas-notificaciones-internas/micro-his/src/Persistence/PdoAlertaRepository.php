<?php
declare(strict_types=1);

namespace AlertasCriticas\Persistence;

use AlertasCriticas\Domain\{Alerta, AlertaRepository, ErrorPersistenciaException, EventoHistorial};

/**
 * Adaptador PDO del puerto AlertaRepository. Tablas: propuesta del Micro-HIS (el esquema real
 * de HOSPITAL para alertas no está publicado). Siempre prepare() + execute().
 */
final class PdoAlertaRepository implements AlertaRepository
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private \PDO $pdo) {}

    public function guardar(Alerta $alerta): void
    {
        $historial = $alerta->historial();
        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare(
                'INSERT INTO alerta_critica (id, tenant_id, codigo_paciente, servicio, origen, id_origen, prioridad,
                     descripcion, estado, nivel, plazo_desde, escalamiento_agotado, creada_en, actualizada_en)
                 VALUES (:id, :tenant, :paciente, :servicio, :origen, :id_origen, :prioridad, :descripcion,
                     :estado, :nivel, :plazo, :agotado, :creada, :actualizada)
                 ON CONFLICT (id) DO UPDATE SET estado = EXCLUDED.estado, nivel = EXCLUDED.nivel,
                     plazo_desde = EXCLUDED.plazo_desde, escalamiento_agotado = EXCLUDED.escalamiento_agotado,
                     actualizada_en = EXCLUDED.actualizada_en'
            )->execute([
                ':id' => $alerta->id, ':tenant' => $alerta->tenant, ':paciente' => $alerta->paciente->valor,
                ':servicio' => $alerta->servicio, ':origen' => $alerta->origen, ':id_origen' => $alerta->idOrigen,
                ':prioridad' => $alerta->prioridad->value, ':descripcion' => $alerta->descripcion,
                ':estado' => $alerta->estado()->value, ':nivel' => $alerta->nivel(),
                ':plazo' => $alerta->plazoDesde()?->format(DATE_ATOM),
                ':agotado' => $alerta->escalamientoAgotado() ? 'true' : 'false',
                ':creada' => $historial[0]->momento->format(DATE_ATOM),
                ':actualizada' => end($historial)->momento->format(DATE_ATOM),
            ]);
            $destinatario = $this->pdo->prepare(
                'INSERT INTO alerta_destinatario (alerta_id, usuario, nivel) VALUES (:a, :u, :n) ON CONFLICT DO NOTHING');
            foreach ($alerta->destinatarios() as $usuario => $nivel) {
                $destinatario->execute([':a' => $alerta->id, ':u' => $usuario, ':n' => $nivel]);
            }
            // RN-10: el historial solo crece; los eventos ya guardados no se tocan.
            $evento = $this->pdo->prepare(
                'INSERT INTO alerta_evento (alerta_id, orden, evento, actor, detalle, momento)
                 VALUES (:a, :o, :e, :r, :d, :m) ON CONFLICT (alerta_id, orden) DO NOTHING');
            foreach ($historial as $e) {
                $evento->execute([':a' => $alerta->id, ':o' => $e->orden, ':e' => $e->evento, ':r' => $e->actor,
                    ':d' => $e->detalle, ':m' => $e->momento->format(DATE_ATOM)]);
            }
            $this->pdo->commit();
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new ErrorPersistenciaException('No se pudo guardar la alerta: ' . $e->getMessage(), 0, $e);
        }
    }

    public function buscar(string $id, string $tenant): ?Alerta
    {
        if (!preg_match(self::UUID, $id)) {
            return null;
        }
        return $this->uno('SELECT * FROM alerta_critica WHERE id = :id AND tenant_id = :tenant',
            [':id' => $id, ':tenant' => $tenant]);
    }

    public function buscarPorOrigen(string $tenant, string $origen, string $idOrigen): ?Alerta
    {
        return $this->uno('SELECT * FROM alerta_critica WHERE tenant_id = :tenant AND origen = :origen AND id_origen = :id_origen',
            [':tenant' => $tenant, ':origen' => $origen, ':id_origen' => $idOrigen]);
    }

    public function pendientesDeAcuse(): array
    {
        return $this->varios("SELECT * FROM alerta_critica WHERE estado IN ('ENVIADA', 'ESCALADA')
                              AND NOT escalamiento_agotado ORDER BY plazo_desde", []);
    }

    public function listar(string $tenant): array
    {
        return $this->varios('SELECT * FROM alerta_critica WHERE tenant_id = :tenant ORDER BY creada_en DESC LIMIT 100',
            [':tenant' => $tenant]);
    }

    private function uno(string $sql, array $parametros): ?Alerta
    {
        return $this->varios($sql, $parametros)[0] ?? null;
    }

    /** @return Alerta[] */
    private function varios(string $sql, array $parametros): array
    {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($parametros);
            return array_map(fn (array $fila) => $this->hidratar($fila), $st->fetchAll());
        } catch (\PDOException $e) {
            throw new ErrorPersistenciaException('No se pudo leer alertas: ' . $e->getMessage(), 0, $e);
        }
    }

    private function hidratar(array $fila): Alerta
    {
        $st = $this->pdo->prepare('SELECT usuario, nivel FROM alerta_destinatario WHERE alerta_id = :id ORDER BY nivel, usuario');
        $st->execute([':id' => $fila['id']]);
        $destinatarios = array_map('intval', array_column($st->fetchAll(), 'nivel', 'usuario'));
        $st = $this->pdo->prepare('SELECT orden, evento, actor, detalle, momento FROM alerta_evento WHERE alerta_id = :id ORDER BY orden');
        $st->execute([':id' => $fila['id']]);
        $historial = array_map(fn (array $e) => new EventoHistorial((int) $e['orden'], $e['evento'], $e['actor'],
            new \DateTimeImmutable($e['momento']), (string) $e['detalle']), $st->fetchAll());
        return Alerta::reconstituir(['tenant' => $fila['tenant_id']] + $fila, $destinatarios, $historial);
    }
}

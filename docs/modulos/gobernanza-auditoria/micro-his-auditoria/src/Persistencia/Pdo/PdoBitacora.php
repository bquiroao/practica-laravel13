<?php

declare(strict_types=1);

namespace Auditoria\Persistencia\Pdo;

use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Modelo\ConsultaMovimientos;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Puertos\Bitacora;

/**
 * audit_logs en PostgreSQL. Solo sentencias preparadas con parámetros con nombre: ningún valor
 * se concatena en el SQL (RNF-07). El WHERE de la consulta se compone con los fragmentos que
 * trae cada filtro (semana 2). No hay UPDATE ni DELETE: además, el trigger de schema.sql los rechaza.
 */
final class PdoBitacora implements Bitacora
{
    private const COLUMNAS = 'id, emisor, event_id, hospital_id, usuario_id, modulo, accion, ocurrido_en, resultado, recurso,
        correlation_id, detalle, secuencia, sello';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function agregar(Movimiento $m): Movimiento
    {
        $id = $this->ejecutar(
            'INSERT INTO audit_logs (emisor, event_id, hospital_id, usuario_id, modulo, accion, ocurrido_en, resultado, recurso,
                correlation_id, detalle, secuencia, sello)
             VALUES (:emisor, :event_id, :hospital_id, :usuario_id, :modulo, :accion, CAST(:ocurrido_en AS timestamptz), :resultado,
                :recurso, :correlation_id, CAST(:detalle AS jsonb), :secuencia, :sello)
             RETURNING id',
            ['emisor' => $m->emisor, 'event_id' => $m->eventId, 'hospital_id' => $m->hospital, 'usuario_id' => $m->usuario,
                'modulo' => $m->modulo, 'accion' => $m->accion, 'ocurrido_en' => $m->ocurridoEn->format('Y-m-d H:i:sP'),
                'resultado' => $m->resultado, 'recurso' => $m->recurso, 'correlation_id' => $m->correlationId,
                'detalle' => json_encode((object) $m->detalle, JSON_UNESCAPED_UNICODE), 'secuencia' => $m->secuencia, 'sello' => $m->sello],
        )->fetchColumn();

        return $m->conId((int) $id);
    }

    public function bloquearCadena(?string $hospital): void
    {
        // Candado transaccional por cadena: dos registros simultáneos del mismo hospital se serializan.
        $this->ejecutar('SELECT pg_advisory_xact_lock(hashtextextended(:clave, 0))', ['clave' => 'audit_logs:' . ($hospital ?? '*')]);
    }

    public function porEvento(string $emisor, string $eventId): ?Movimiento
    {
        return $this->uno('SELECT ' . self::COLUMNAS . ' FROM audit_logs WHERE emisor = :emisor AND event_id = :event_id',
            ['emisor' => $emisor, 'event_id' => $eventId]);
    }

    public function porId(int $id): ?Movimiento
    {
        return $this->uno('SELECT ' . self::COLUMNAS . ' FROM audit_logs WHERE id = :id', ['id' => $id]);
    }

    public function ultimo(?string $hospital): ?Movimiento
    {
        return $this->uno('SELECT ' . self::COLUMNAS . ' FROM audit_logs WHERE hospital_id IS NOT DISTINCT FROM CAST(:hospital AS varchar)
            ORDER BY secuencia DESC LIMIT 1', ['hospital' => $hospital]);
    }

    public function buscar(ConsultaMovimientos $consulta): array
    {
        $condiciones = [];
        $parametros = ['limite' => $consulta->limite];
        if (!$consulta->red) {
            $condiciones[] = 'hospital_id = :alcance_hospital';
            $parametros['alcance_hospital'] = $consulta->hospital;
        }
        if ($consulta->despuesDe !== null) {
            $condiciones[] = '(ocurrido_en, id) < (CAST(:cursor_fecha AS timestamptz), :cursor_id)';
            $parametros['cursor_fecha'] = $consulta->despuesDe[0]->format('Y-m-d H:i:sP');
            $parametros['cursor_id'] = $consulta->despuesDe[1];
        }
        foreach ($consulta->filtros as $filtro) {
            [$fragmento, $valores] = $filtro->sql($consulta->parametros);
            $condiciones[] = '(' . $fragmento . ')';
            $parametros += $valores;
        }
        $sql = 'SELECT ' . self::COLUMNAS . ' FROM audit_logs'
            . ($condiciones === [] ? '' : ' WHERE ' . implode(' AND ', $condiciones))
            . ' ORDER BY ocurrido_en DESC, id DESC LIMIT :limite';

        return array_map($this->hidratar(...), $this->ejecutar($sql, $parametros)->fetchAll());
    }

    public function cadena(?string $hospital): array
    {
        return array_map($this->hidratar(...), $this->ejecutar('SELECT ' . self::COLUMNAS . ' FROM audit_logs
            WHERE hospital_id IS NOT DISTINCT FROM CAST(:hospital AS varchar) ORDER BY secuencia', ['hospital' => $hospital])->fetchAll());
    }

    /** @param array<string, mixed> $parametros */
    private function uno(string $sql, array $parametros): ?Movimiento
    {
        $fila = $this->ejecutar($sql, $parametros)->fetch();

        return $fila === false ? null : $this->hidratar($fila);
    }

    /**
     * @param array<string, mixed> $parametros
     * @throws ErrorPersistencia sin el SQL ni los valores en el mensaje
     */
    private function ejecutar(string $sql, array $parametros): \PDOStatement
    {
        try {
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($parametros);

            return $sentencia;
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('La bitácora no está disponible.', $e->errorInfo[0] ?? (string) $e->getCode(), $e);
        }
    }

    /** @param array<string, mixed> $f */
    private function hidratar(array $f): Movimiento
    {
        return Movimiento::reconstituir((int) $f['id'], $f['emisor'], $f['event_id'], $f['hospital_id'], $f['usuario_id'], $f['modulo'],
            $f['accion'], new \DateTimeImmutable($f['ocurrido_en']), $f['resultado'], $f['recurso'], $f['correlation_id'],
            json_decode((string) $f['detalle'], true) ?? [], (int) $f['secuencia'], trim((string) $f['sello']));
    }
}

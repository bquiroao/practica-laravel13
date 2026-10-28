<?php
declare(strict_types=1);

// Persistencia: SQL con sentencias preparadas. Cerrar la estancia de origen, sincronizar beds.status de origen y destino,
// abrir la estancia nueva y marcar la orden aplicada van en UNA transacción (RNF-01). No hay trigger que sincronice
// beds.status con bed_stays (hallazgo H-01 del módulo Verificación BD): este módulo lo hace aquí.
namespace Traslados\Persistencia;

use Traslados\Aplicacion\ErrorPersistencia;
use Traslados\Dominio\Resultado;

final class MovimientosPdo {
    public function __construct(private \PDO $pdo) { $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); }

    public function episodioAbierto(string $id): ?array {
        return $this->uno('SELECT care_episode_id FROM care_episodes WHERE care_episode_id = ? AND status = ?', [$id, 'open']);
    }

    public function ordenVigente(string $episodio, string $tipo): ?array {
        return $this->uno('SELECT * FROM ordenes_movimiento WHERE care_episode_id = ? AND tipo = ? AND estado = ?', [$episodio, $tipo, 'vigente']);
    }

    public function estanciaAbierta(string $episodio): ?array {
        return $this->uno('SELECT bed_stay_id, bed_id FROM bed_stays WHERE care_episode_id = ? AND ended_at IS NULL', [$episodio]);
    }

    public function cama(string $id): ?array { return $this->uno('SELECT bed_id, status, type FROM beds WHERE bed_id = ?', [$id]); }

    public function aplicar(string $episodio, string $orden, string $tipo, Resultado $r, string $estanciaOrigen, string $estanciaNueva, \DateTimeImmutable $en): void {
        $cuando = $en->format('c');
        try {
            $this->pdo->beginTransaction();
            $this->ejecutar('UPDATE bed_stays SET ended_at = ?, reason = ? WHERE bed_stay_id = ?', [$cuando, $tipo, $estanciaOrigen]);
            $this->ejecutar('UPDATE beds SET status = ? WHERE bed_id = ?', ['maintenance', $r->origenId]);
            if ($r->destinoId !== null) {
                $this->ejecutar('INSERT INTO bed_stays (bed_stay_id, care_episode_id, bed_id, started_at) VALUES (?, ?, ?, ?)', [$estanciaNueva, $episodio, $r->destinoId, $cuando]);
                $this->ejecutar('UPDATE beds SET status = ? WHERE bed_id = ?', ['occupied', $r->destinoId]);
            }
            $this->ejecutar('UPDATE ordenes_movimiento SET estado = ? WHERE orden_id = ?', ['aplicada', $orden]);
            if ($tipo === 'alta') { $this->ejecutar('UPDATE care_episodes SET status = ?, ended_at = ? WHERE care_episode_id = ?', ['closed', $cuando, $episodio]); }
            $this->pdo->commit();
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw new ErrorPersistencia('No se pudo aplicar el movimiento', 0, $e);
        }
    }

    private function ejecutar(string $sql, array $parametros): void { $this->pdo->prepare($sql)->execute($parametros); }

    private function uno(string $sql, array $parametros): ?array {
        try { $c = $this->pdo->prepare($sql); $c->execute($parametros); return $c->fetch(\PDO::FETCH_ASSOC) ?: null; }
        catch (\PDOException $e) { throw new ErrorPersistencia('No se pudo leer la base', 0, $e); }
    }
}

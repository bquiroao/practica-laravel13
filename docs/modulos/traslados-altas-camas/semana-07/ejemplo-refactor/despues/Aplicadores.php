<?php
declare(strict_types=1);

// DESPUÉS (semana 7): dos adaptadores que solo ejecutan el plan. Ninguno contiene reglas del negocio.
namespace Traslados\Refactor;

use Traslados\Aplicacion\ErrorPersistencia;

final class AplicadorPdo {
    public function __construct(private \PDO $pdo) { $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); }

    /** @param list<Cambio> $plan todo o nada */
    public function aplicar(array $plan): void {
        try {
            $this->pdo->beginTransaction();
            foreach ($plan as $c) {
                [$sql, $p] = match (true) {
                    $c instanceof CerrarEstancia => ['UPDATE bed_stays SET ended_at = ?, reason = ? WHERE bed_stay_id = ?', [$c->en, $c->motivo, $c->estanciaId]],
                    $c instanceof PonerEstadoCama => ['UPDATE beds SET status = ? WHERE bed_id = ?', [$c->estado, $c->camaId]],
                    $c instanceof AbrirEstancia => ['INSERT INTO bed_stays (bed_stay_id, care_episode_id, bed_id, started_at) VALUES (?, ?, ?, ?)', [$c->estanciaId, $c->episodioId, $c->camaId, $c->en]],
                    $c instanceof PonerEstadoOrden => ['UPDATE ordenes_movimiento SET estado = ? WHERE orden_id = ?', [$c->estado, $c->ordenId]],
                    $c instanceof CerrarEpisodio => ['UPDATE care_episodes SET status = ?, ended_at = ? WHERE care_episode_id = ?', [$c->estado, $c->en, $c->episodioId]],
                };
                $this->pdo->prepare($sql)->execute($p);
            }
            $this->pdo->commit();
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw new ErrorPersistencia('No se pudo aplicar el movimiento', 0, $e);
        }
    }
}

final class AplicadorMemoria {
    /** Mismos datos que las tablas: camas[id]=[status], estancias[id]=[...], episodios[id]=[status], ordenes[id]=[estado]. */
    public function __construct(public array $camas, public array $estancias, public array $episodios, public array $ordenes) {}

    public function aplicar(array $plan): void {
        $antes = [$this->camas, $this->estancias, $this->episodios, $this->ordenes];
        try {
            foreach ($plan as $c) {
                match (true) {
                    $c instanceof CerrarEstancia => $this->cerrar($c),
                    $c instanceof PonerEstadoCama => $this->camas[$c->camaId]['status'] = $c->estado,
                    $c instanceof AbrirEstancia => $this->abrir($c),
                    $c instanceof PonerEstadoOrden => $this->ordenes[$c->ordenId]['estado'] = $c->estado,
                    $c instanceof CerrarEpisodio => $this->episodios[$c->episodioId] = ['status' => $c->estado, 'ended_at' => $c->en],
                };
            }
        } catch (\Throwable $e) { [$this->camas, $this->estancias, $this->episodios, $this->ordenes] = $antes; throw new ErrorPersistencia('No se pudo aplicar el movimiento', 0, $e); }
    }

    private function abrir(AbrirEstancia $c): void {
        if (isset($this->estancias[$c->estanciaId])) { throw new \RuntimeException('llave repetida'); } // igual que la llave primaria de la base
        $this->estancias[$c->estanciaId] = ['care_episode_id' => $c->episodioId, 'bed_id' => $c->camaId, 'started_at' => $c->en, 'ended_at' => null, 'reason' => null];
    }

    private function cerrar(CerrarEstancia $c): void {
        if (!isset($this->estancias[$c->estanciaId])) { throw new \RuntimeException('estancia inexistente'); }
        $this->estancias[$c->estanciaId]['ended_at'] = $c->en;
        $this->estancias[$c->estanciaId]['reason'] = $c->motivo;
    }
}

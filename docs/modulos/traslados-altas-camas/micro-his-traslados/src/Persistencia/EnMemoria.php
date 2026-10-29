<?php
declare(strict_types=1);

// Adaptador en memoria: mismo contrato que MovimientosPdo, sin base. Sirve para pruebas rápidas, demostración y como doble que falla.
namespace Traslados\Persistencia;

use Traslados\Aplicacion\{ErrorPersistencia, FuenteClinica, RepositorioMovimientos};
use Traslados\Dominio\Resultado;

final class EnMemoria implements RepositorioMovimientos, FuenteClinica {
    public bool $fallarAlAplicar = false;

    /** episodios: id => estado · ordenes: id => [episodio, tipo, estado, requisito_cama, tipo_alta, motivo] · estancias: id => [episodio, cama, abierta] · camas: id => [estado, tipo] */
    public function __construct(private array $episodios, private array $ordenes, private array $estancias, private array $camas) {}

    /** Los mismos datos de demostración que sql/esquema.sql. */
    public static function demo(): self {
        return new self(
            ['epi-1' => 'open', 'epi-2' => 'open', 'epi-3' => 'open', 'epi-4' => 'open', 'epi-9' => 'open', 'epi-cerrado' => 'closed'],
            ['ord-1' => ['epi-1', 'traslado', 'vigente', 'cualquiera', null, null], 'ord-2' => ['epi-2', 'alta', 'vigente', null, 'medica', 'Mejoría clínica'],
             'ord-3' => ['epi-3', 'traslado', 'vigente', 'uci', null, null], 'ord-4' => ['epi-4', 'alta', 'vigente', null, 'medica', ''],
             'ord-5' => ['epi-9', 'traslado', 'vigente', 'cualquiera', null, null]],
            ['stay-1' => ['epi-1', 'bed-a1', true], 'stay-2' => ['epi-2', 'bed-a3', true], 'stay-3' => ['epi-3', 'bed-a4', true], 'stay-4' => ['epi-4', 'bed-a5', true]],
            ['bed-a1' => ['occupied', 'general'], 'bed-a2' => ['available', 'general'], 'bed-a3' => ['occupied', 'general'], 'bed-a4' => ['occupied', 'general'],
             'bed-a5' => ['occupied', 'general'], 'bed-u1' => ['available', 'uci'], 'bed-m1' => ['maintenance', 'general']]);
    }

    public function episodioAbierto(string $id): ?array { return ($this->episodios[$id] ?? null) === 'open' ? ['care_episode_id' => $id] : null; }

    public function ordenVigente(string $episodio, string $tipo): ?array {
        foreach ($this->ordenes as $id => [$epi, $t, $estado, $requisito, $tipoAlta, $motivo]) {
            if ($epi === $episodio && $t === $tipo && $estado === 'vigente') { return ['orden_id' => $id, 'requisito_cama' => $requisito, 'tipo_alta' => $tipoAlta, 'motivo' => $motivo]; }
        }
        return null;
    }

    public function estanciaAbierta(string $episodio): ?array {
        foreach ($this->estancias as $id => [$epi, $cama, $abierta]) { if ($epi === $episodio && $abierta) { return ['bed_stay_id' => $id, 'bed_id' => $cama]; } }
        return null;
    }

    public function cama(string $id): ?array { return isset($this->camas[$id]) ? ['bed_id' => $id, 'status' => $this->camas[$id][0], 'type' => $this->camas[$id][1]] : null; }

    public function aplicar(string $episodio, string $orden, string $tipo, Resultado $r, string $estanciaOrigen, string $estanciaNueva, \DateTimeImmutable $en): void {
        if ($this->fallarAlAplicar) { throw new ErrorPersistencia('Falla simulada al aplicar'); } // antes de tocar nada: todo o nada
        $this->estancias[$estanciaOrigen][2] = false;
        $this->camas[$r->origenId][0] = 'maintenance';
        if ($r->destinoId !== null) { $this->estancias[$estanciaNueva] = [$episodio, $r->destinoId, true]; $this->camas[$r->destinoId][0] = 'occupied'; }
        $this->ordenes[$orden][2] = 'aplicada';
        if ($tipo === 'alta') { $this->episodios[$episodio] = 'closed'; }
    }
}

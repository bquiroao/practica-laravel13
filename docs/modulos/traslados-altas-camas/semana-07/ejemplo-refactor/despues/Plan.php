<?php
declare(strict_types=1);

// DESPUÉS (semana 7): qué le pasa a las camas, la estancia, la orden y el episodio al aplicar un movimiento se decide UNA vez, en el dominio.
// Devuelve una lista de cambios (datos puros). Los adaptadores solo los ejecutan: no saben qué significa 'maintenance' ni 'alta'.
namespace Traslados\Refactor;

use Traslados\Dominio\Resultado;

/** Los mismos datos que hoy recibe RepositorioMovimientos::aplicar() de la semana 4, juntos en un objeto. */
final class MovimientoConfirmado {
    public function __construct(public readonly string $episodioId, public readonly string $ordenId, public readonly string $tipo, public readonly Resultado $resultado,
                                public readonly string $estanciaOrigenId, public readonly string $estanciaNuevaId, public readonly \DateTimeImmutable $en) {}
}

interface Cambio {}
final class CerrarEstancia implements Cambio { public function __construct(public readonly string $estanciaId, public readonly string $en, public readonly string $motivo) {} }
final class PonerEstadoCama implements Cambio { public function __construct(public readonly string $camaId, public readonly string $estado) {} }
final class AbrirEstancia implements Cambio {
    public function __construct(public readonly string $estanciaId, public readonly string $episodioId, public readonly string $camaId, public readonly string $en) {}
}
final class PonerEstadoOrden implements Cambio { public function __construct(public readonly string $ordenId, public readonly string $estado) {} }
final class CerrarEpisodio implements Cambio { public function __construct(public readonly string $episodioId, public readonly string $estado, public readonly string $en) {} }

final class PlanDeMovimiento {
    public function __construct(private string $estadoCamaLiberada = 'maintenance', private string $estadoCamaOcupada = 'occupied',
                                private string $estadoOrdenCumplida = 'aplicada', private string $tipoQueCierraEpisodio = 'alta', private string $estadoEpisodioAlAlta = 'closed') {}

    /** @return list<Cambio> en el orden en que deben ejecutarse, todos dentro de una sola transacción */
    public function para(MovimientoConfirmado $m): array {
        $cuando = $m->en->format('c'); $r = $m->resultado;
        $plan = [new CerrarEstancia($m->estanciaOrigenId, $cuando, $m->tipo), new PonerEstadoCama($r->origenId, $this->estadoCamaLiberada)];
        if ($r->destinoId !== null) {
            $plan[] = new AbrirEstancia($m->estanciaNuevaId, $m->episodioId, $r->destinoId, $cuando);
            $plan[] = new PonerEstadoCama($r->destinoId, $this->estadoCamaOcupada);
        }
        $plan[] = new PonerEstadoOrden($m->ordenId, $this->estadoOrdenCumplida);
        if ($m->tipo === $this->tipoQueCierraEpisodio) { $plan[] = new CerrarEpisodio($m->episodioId, $this->estadoEpisodioAlAlta, $cuando); }
        return $plan;
    }
}

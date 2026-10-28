<?php
declare(strict_types=1);

// Aplicación: orquesta CU-02/CU-03 (permiso → episodio → orden vigente → camas → Dominio → guardar). Sin SQL ni HTTP.
// Semana 3: depende directo del adaptador PDO. En la semana 4 se invierte con una interfaz Repository (como en Signos vitales).
namespace Traslados\Aplicacion;

use Traslados\Dominio\{Alta, CamaDestino, CamaOrigen, ErrorDominio, MovimientoDeCama, RequisitoCama, Resultado, TipoAlta, Traslado};
use Traslados\Persistencia\MovimientosPdo;

final class Usuario { public function __construct(public readonly string $id, public readonly string $rol) {} }

final class ErrorPersistencia extends \RuntimeException {}

final class RegistrarMovimiento {
    private \Closure $reloj;
    private \Closure $nuevoId;

    public function __construct(private MovimientosPdo $bd, ?\Closure $reloj = null, ?\Closure $nuevoId = null) {
        $this->reloj = $reloj ?? fn () => new \DateTimeImmutable();
        $this->nuevoId = $nuevoId ?? fn () => bin2hex(random_bytes(8));
    }

    /** $entrada: episodio_id, tipo (traslado|alta) y destino_id (solo traslado: la cama la elige enfermería al ejecutar). */
    public function ejecutar(array $entrada, Usuario $usuario): array {
        if (!in_array($usuario->rol, ['enfermeria', 'admision'], true)) { throw new ErrorDominio('acceso_denegado', 'Solo enfermería o admisión registran movimientos'); }
        $episodio = (string) ($entrada['episodio_id'] ?? '');
        $tipo = (string) ($entrada['tipo'] ?? '');
        if ($this->bd->episodioAbierto($episodio) === null) { throw new ErrorDominio('episodio_no_disponible', 'El episodio no existe o ya está cerrado'); }
        $orden = $this->bd->ordenVigente($episodio, $tipo);
        if ($orden === null) { throw new ErrorDominio('orden_requerida', 'No hay orden médica vigente de ese tipo para este episodio'); }
        $estancia = $this->bd->estanciaAbierta($episodio);
        $origen = $estancia === null ? null : new CamaOrigen($estancia['bed_id'], $estancia['bed_stay_id']);
        $resultado = $this->movimiento($tipo, $orden)->aplicar($origen, $this->destino($tipo, (string) ($entrada['destino_id'] ?? '')));
        if ($resultado->fueAplicado()) {
            $this->bd->aplicar($episodio, $orden['orden_id'], $tipo, $resultado, $estancia['bed_stay_id'], ($this->nuevoId)(), ($this->reloj)());
        }
        return ['aplicado' => $resultado->fueAplicado(), 'rechazo' => $resultado->rechazo, 'origen_id' => $resultado->origenId, 'destino_id' => $resultado->destinoId];
    }

    private function movimiento(string $tipo, array $orden): MovimientoDeCama {
        return $tipo === 'traslado' ? new Traslado(RequisitoCama::from($orden['requisito_cama']))
                                    : new Alta(TipoAlta::from($orden['tipo_alta']), (string) $orden['motivo']);
    }

    private function destino(string $tipo, string $camaId): ?CamaDestino {
        $fila = ($tipo === 'traslado' && $camaId !== '') ? $this->bd->cama($camaId) : null;
        return $fila === null ? null : new CamaDestino($fila['bed_id'], $fila['status'], $fila['type']);
    }
}

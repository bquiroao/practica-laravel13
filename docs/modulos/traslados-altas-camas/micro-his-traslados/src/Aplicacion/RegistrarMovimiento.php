<?php
declare(strict_types=1);

// Aplicación: orquesta CU-02/CU-03 (permiso → episodio → orden vigente → camas → Dominio → guardar). Sin SQL ni HTTP.
// Semana 4: depende de las interfaces de Puertos.php; el adaptador (base o memoria) se elige afuera.
namespace Traslados\Aplicacion;

use Traslados\Dominio\{Alta, CamaDestino, CamaOrigen, ErrorDominio, MovimientoDeCama, RequisitoCama, Resultado, TipoAlta, Traslado};

final class Usuario { public function __construct(public readonly string $id, public readonly string $rol) {} }

final class ErrorPersistencia extends \RuntimeException {}

final class RegistrarMovimiento {
    private \Closure $reloj;
    private \Closure $nuevoId;

    public function __construct(private RepositorioMovimientos $movimientos, private FuenteClinica $clinica, ?\Closure $reloj = null, ?\Closure $nuevoId = null) {
        $this->reloj = $reloj ?? fn () => new \DateTimeImmutable();
        $this->nuevoId = $nuevoId ?? fn () => bin2hex(random_bytes(8));
    }

    /** $entrada: episodio_id, tipo (traslado|alta) y destino_id (solo traslado: la cama la elige enfermería al ejecutar). */
    public function ejecutar(array $entrada, Usuario $usuario, ?string $clave = null): array {
        if (!in_array($usuario->rol, ['enfermeria', 'admision'], true)) { throw new ErrorDominio('acceso_denegado', 'Solo enfermería o admisión registran movimientos'); }
        $episodio = (string) ($entrada['episodio_id'] ?? '');
        $tipo = (string) ($entrada['tipo'] ?? '');
        $previa = $clave === null ? null : $this->movimientos->ordenPorClave($clave); // reintento: la clave ya resolvió un movimiento
        if ($previa !== null) {
            if ($previa['care_episode_id'] !== $episodio || $previa['tipo'] !== $tipo) { throw new ErrorDominio('clave_reutilizada', 'Esa clave de idempotencia ya se usó en otro movimiento'); }
            return ['aplicado' => true, 'rechazo' => null, 'origen_id' => $previa['origen_id'], 'destino_id' => $previa['destino_id'], 'repetida' => true];
        }
        if ($this->clinica->episodioAbierto($episodio) === null) { throw new ErrorDominio('episodio_no_disponible', 'El episodio no existe o ya está cerrado'); }
        $orden = $this->movimientos->ordenVigente($episodio, $tipo);
        if ($orden === null) { throw new ErrorDominio('orden_requerida', 'No hay orden médica vigente de ese tipo para este episodio'); }
        $estancia = $this->clinica->estanciaAbierta($episodio);
        $origen = $estancia === null ? null : new CamaOrigen($estancia['bed_id'], $estancia['bed_stay_id']);
        $resultado = $this->movimiento($tipo, $orden)->aplicar($origen, $this->destino($tipo, (string) ($entrada['destino_id'] ?? '')));
        if ($resultado->fueAplicado()) {
            $this->movimientos->aplicar($episodio, $orden['orden_id'], $tipo, $resultado, $estancia['bed_stay_id'], ($this->nuevoId)(), ($this->reloj)(), $clave);
        }
        return ['aplicado' => $resultado->fueAplicado(), 'rechazo' => $resultado->rechazo, 'origen_id' => $resultado->origenId, 'destino_id' => $resultado->destinoId];
    }

    private function movimiento(string $tipo, array $orden): MovimientoDeCama {
        return $tipo === 'traslado' ? new Traslado(RequisitoCama::from($orden['requisito_cama']))
                                    : new Alta(TipoAlta::from($orden['tipo_alta']), (string) $orden['motivo']);
    }

    private function destino(string $tipo, string $camaId): ?CamaDestino {
        $fila = ($tipo === 'traslado' && $camaId !== '') ? $this->clinica->cama($camaId) : null;
        return $fila === null ? null : new CamaDestino($fila['bed_id'], $fila['status'], $fila['type']);
    }
}

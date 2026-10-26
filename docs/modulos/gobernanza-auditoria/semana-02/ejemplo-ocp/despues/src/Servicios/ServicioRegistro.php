<?php

declare(strict_types=1);

namespace Auditoria\Servicios;

use Auditoria\Contratos\Bitacora;
use Auditoria\Contratos\PoliticaAcceso;
use Auditoria\Contratos\ReglaMovimiento;
use Auditoria\Contratos\Transaccion;
use Auditoria\Dominio\ErrorPersistencia;
use Auditoria\Dominio\Movimiento;
use Auditoria\Dominio\Resultado;
use Auditoria\Dominio\Sello;
use Auditoria\Dominio\SolicitudRegistro;

/**
 * CU-01. Cerrado a modificación: recorre las reglas que recibe sin saber cuáles son.
 * Lo que NO es intercambiable vive aquí: permiso, idempotencia (R-04), sello (R-03)
 * y transacción (R-10).
 */
final class ServicioRegistro
{
    /** @param iterable<ReglaMovimiento> $reglas */
    public function __construct(
        private readonly iterable $reglas,
        private readonly Bitacora $bitacora,
        private readonly Transaccion $transaccion,
        private readonly PoliticaAcceso $acceso,
    ) {
    }

    public function registrar(SolicitudRegistro $solicitud): Resultado
    {
        if (!$this->acceso->permite($solicitud->emisor, $solicitud->hospitalToken, 'auditoria.registrar')) {
            return Resultado::error('prohibido', 'ACCESO_DENEGADO');
        }
        foreach ($this->reglas as $regla) {
            $rechazo = $regla->evaluar($solicitud);
            if ($rechazo !== null) {
                return $rechazo->comoResultado();
            }
        }
        $nuevo = Movimiento::desdeSolicitud($solicitud);

        try {
            return $this->transaccion->ejecutar(function () use ($nuevo): Resultado {
                $existente = $this->bitacora->porEvento($nuevo->emisor, $nuevo->eventId);
                if ($existente !== null) {
                    return $existente->mismoContenidoQue($nuevo)
                        ? Resultado::ok(['id' => $existente->id], 'YA_REGISTRADO')
                        : Resultado::error('conflicto', 'EVENTO_EN_CONFLICTO');
                }
                $sellado = $this->anexar($nuevo);

                return Resultado::creado(['id' => $sellado->id, 'secuencia' => $sellado->secuencia, 'sello' => $sellado->sello]);
            });
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }

    /**
     * Agrega al final de la cadena del hospital. Lo usa también el propio módulo para la
     * meta-auditoría (R-08); debe llamarse dentro de una transacción.
     *
     * @throws ErrorPersistencia
     */
    public function anexar(Movimiento $movimiento): Movimiento
    {
        $anterior = $this->bitacora->ultimo($movimiento->hospital);
        $secuencia = ($anterior?->secuencia ?? 0) + 1;
        $sellado = $movimiento->sellado($this->bitacora->siguienteId(), $secuencia,
            Sello::calcular($anterior?->sello ?? Sello::GENESIS, $secuencia, $movimiento));
        $this->bitacora->agregar($sellado);

        return $sellado;
    }
}

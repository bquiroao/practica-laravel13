<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\CasosDeUso;

use Auditoria\Aplicacion\Comandos\ComandoRegistrar;
use Auditoria\Aplicacion\Puertos\PoliticaAcceso;
use Auditoria\Aplicacion\Puertos\Transaccion;
use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Aplicacion\Servicios\CadenaSellada;
use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Modelo\SolicitudRegistro;
use Auditoria\Dominio\Puertos\Bitacora;
use Auditoria\Dominio\Reglas\ReglaMovimiento;

/**
 * CU-01: permiso → reglas (OCP, semana 2) → idempotencia (R-04) → sello y alta (R-03), todo o nada (R-10).
 */
final class RegistrarMovimiento
{
    public const PERMISO = 'auditoria.registrar';

    /** @param iterable<ReglaMovimiento> $reglas */
    public function __construct(
        private readonly iterable $reglas,
        private readonly Bitacora $bitacora,
        private readonly CadenaSellada $cadena,
        private readonly Transaccion $transaccion,
        private readonly PoliticaAcceso $acceso,
    ) {
    }

    public function ejecutar(ComandoRegistrar $comando): Resultado
    {
        if (!$this->acceso->permite($comando->emisor, $comando->hospitalToken, self::PERMISO)) {
            return Resultado::accesoDenegado();
        }
        $solicitud = new SolicitudRegistro($comando->emisor, $comando->hospitalToken, $comando->datos);
        foreach ($this->reglas as $regla) {
            $rechazo = $regla->evaluar($solicitud);
            if ($rechazo !== null) {
                return Resultado::desdeRechazo($rechazo);
            }
        }
        $nuevo = Movimiento::desdeSolicitud($solicitud);
        $corrigeEvento = $comando->datos['corrige_evento'] ?? null;

        try {
            return $this->transaccion->ejecutar(function () use ($nuevo, $corrigeEvento): Resultado {
                // Semana 6: el original se busca SOLO entre los eventos de este emisor y en su mismo hospital;
                // otro hospital u otro emisor responden igual que «no existe» (no se revela nada).
                $original = $corrigeEvento === null ? null : $this->bitacora->porEvento($nuevo->emisor, $corrigeEvento);
                if ($original !== null && $original->hospital !== $nuevo->hospital) {
                    $original = null;
                }
                // La idempotencia va ANTES que «ya corregido»: reintentar la misma corrección no es un conflicto.
                $existente = $this->bitacora->porEvento($nuevo->emisor, $nuevo->eventId);
                if ($existente !== null) {
                    return $existente->mismoContenidoQue($nuevo) && $existente->corrigeA === $original?->id
                        ? Resultado::ok(['id' => $existente->id, 'secuencia' => $existente->secuencia, 'sello' => $existente->sello], 'YA_REGISTRADO')
                        : Resultado::error(Resultado::CONFLICTO, 'EVENTO_EN_CONFLICTO');
                }
                if ($corrigeEvento !== null) {
                    if ($original === null) {
                        return Resultado::error(Resultado::INVALIDO, 'DATOS_INVALIDOS',
                            ['campos' => ['corrige_evento' => 'No hay un movimiento de este emisor en este hospital con ese event_id.']]);
                    }
                    if ($this->bitacora->correccionDe((int) $original->id) !== null) {
                        return Resultado::error(Resultado::CONFLICTO, 'YA_CORREGIDO');
                    }
                    $nuevo = $nuevo->corrigiendo((int) $original->id);
                }
                $guardado = $this->cadena->anexar($nuevo);

                return Resultado::creado(['id' => $guardado->id, 'secuencia' => $guardado->secuencia, 'sello' => $guardado->sello]);
            });
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }
}

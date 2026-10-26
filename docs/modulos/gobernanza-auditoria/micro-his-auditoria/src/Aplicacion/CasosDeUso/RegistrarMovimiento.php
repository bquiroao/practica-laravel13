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

        try {
            return $this->transaccion->ejecutar(function () use ($nuevo): Resultado {
                $existente = $this->bitacora->porEvento($nuevo->emisor, $nuevo->eventId);
                if ($existente !== null) {
                    return $existente->mismoContenidoQue($nuevo)
                        ? Resultado::ok(['id' => $existente->id, 'secuencia' => $existente->secuencia, 'sello' => $existente->sello], 'YA_REGISTRADO')
                        : Resultado::error(Resultado::CONFLICTO, 'EVENTO_EN_CONFLICTO');
                }
                $guardado = $this->cadena->anexar($nuevo);

                return Resultado::creado(['id' => $guardado->id, 'secuencia' => $guardado->secuencia, 'sello' => $guardado->sello]);
            });
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }
}

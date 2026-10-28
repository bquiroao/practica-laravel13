<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\CasosDeUso;

use Auditoria\Aplicacion\Comandos\ComandoDetalle;
use Auditoria\Aplicacion\Puertos\Transaccion;
use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Aplicacion\Servicios\AutorizacionLectura;
use Auditoria\Aplicacion\Servicios\CadenaSellada;
use Auditoria\Aplicacion\Servicios\ProyeccionMovimientos;
use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Modelo\Alcance;
use Auditoria\Dominio\Puertos\Bitacora;

/** CU-03: el mismo 404 si no existe o si está fuera del alcance (E-09); el detalle también se audita (R-08). */
final class VerDetalleMovimiento
{
    public function __construct(
        private readonly Bitacora $bitacora,
        private readonly CadenaSellada $cadena,
        private readonly Transaccion $transaccion,
        private readonly AutorizacionLectura $autorizacion,
        private readonly ProyeccionMovimientos $proyeccion,
    ) {
    }

    public function ejecutar(ComandoDetalle $comando): Resultado
    {
        $alcance = Alcance::desdeTenant($comando->hospital);
        if (!$this->autorizacion->permite($comando->actor, $alcance)) {
            return Resultado::accesoDenegado();
        }

        try {
            return $this->transaccion->ejecutar(function () use ($comando, $alcance): Resultado {
                $movimiento = $this->bitacora->porId($comando->id);
                if ($movimiento === null || !$alcance->incluye($movimiento)) {
                    return Resultado::error(Resultado::NO_ENCONTRADO, 'MOVIMIENTO_NO_ENCONTRADO');
                }
                $this->cadena->auditarLectura($alcance->hospital, $comando->actor, 'AUDITORIA_CONSULTADA',
                    ['alcance' => $alcance->etiqueta(), 'detalle' => $comando->id]);

                return Resultado::ok(['movimiento' => $this->proyeccion->completo($movimiento)]);
            });
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }
}

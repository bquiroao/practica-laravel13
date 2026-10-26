<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\CasosDeUso;

use Auditoria\Aplicacion\Comandos\ComandoDetalle;
use Auditoria\Aplicacion\Puertos\PoliticaAcceso;
use Auditoria\Aplicacion\Puertos\Transaccion;
use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Aplicacion\Servicios\CadenaSellada;
use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Puertos\Bitacora;

/** CU-03: el mismo 404 si no existe o si está fuera del alcance (E-09); el detalle también se audita (R-08). */
final class VerDetalleMovimiento
{
    public function __construct(
        private readonly Bitacora $bitacora,
        private readonly CadenaSellada $cadena,
        private readonly Transaccion $transaccion,
        private readonly PoliticaAcceso $acceso,
    ) {
    }

    public function ejecutar(ComandoDetalle $comando): Resultado
    {
        $red = $comando->hospital === PoliticaAcceso::RED;
        if (!$this->acceso->permite($comando->actor, $comando->hospital, $red ? ConsultarMovimientos::PERMISO_RED : ConsultarMovimientos::PERMISO)) {
            return Resultado::accesoDenegado();
        }

        try {
            return $this->transaccion->ejecutar(function () use ($comando, $red): Resultado {
                $movimiento = $this->bitacora->porId($comando->id);
                if ($movimiento === null || (!$red && $movimiento->hospital !== $comando->hospital)) {
                    return Resultado::error(Resultado::NO_ENCONTRADO, 'MOVIMIENTO_NO_ENCONTRADO');
                }
                $this->cadena->auditarLectura($red ? null : $comando->hospital, $comando->actor, 'AUDITORIA_CONSULTADA',
                    ['alcance' => $red ? 'red' : 'hospital', 'detalle' => $comando->id]);

                return Resultado::ok(['movimiento' => $movimiento->completo()]);
            });
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }
}

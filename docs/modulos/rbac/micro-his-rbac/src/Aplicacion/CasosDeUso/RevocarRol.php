<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\CasosDeUso;

use Rbac\Aplicacion\Comandos\ComandoRevocarRol;
use Rbac\Aplicacion\Resultados\ResultadoRevocacion;
use Rbac\Aplicacion\Servicios\Desenlace;
use Rbac\Aplicacion\Servicios\RegistradorDeCambios;
use Rbac\Dominio\Cambios\RevocacionDeRol;
use Rbac\Dominio\Modelo\SolicitudRevocacion;
use Rbac\Dominio\Servicios\PoliticaRevocacion;

/**
 * CU-07 Revocar rol (semana 6).
 *
 * Mismo esqueleto que AsignarRol: autorizar «roles.revocar», validar con el Dominio y
 * pedir al RegistradorDeCambios que aplique la revocación con su evidencia (R-07).
 */
final class RevocarRol
{
    public const PERMISO_REQUERIDO = 'roles.revocar';

    public function __construct(
        private readonly AutorizarOperacion $autorizar,
        private readonly PoliticaRevocacion $politica,
        private readonly RegistradorDeCambios $cambios,
        private readonly \Closure $generarCorrelacion,
        private readonly \Closure $reloj,
    ) {
    }

    public function ejecutar(ComandoRevocarRol $comando): ResultadoRevocacion
    {
        $autorizacion = $this->autorizar->exigir($comando->adminId, $comando->tenantToken, $comando->hospitalId, self::PERMISO_REQUERIDO, $comando->ip);
        if (!$autorizacion->permitido) {
            return ResultadoRevocacion::denegado($autorizacion->correlationId);
        }

        $solicitud = new SolicitudRevocacion(
            $comando->adminId,
            $comando->hospitalId,
            $comando->usuarioDestinoId,
            $comando->rol,
            $comando->motivo,
            ($this->reloj)(),
        );

        $error = $this->politica->evaluar($solicitud); // fail-closed dentro del Dominio
        if ($error !== null) {
            return ResultadoRevocacion::rechazado($error->codigo, $error->codigoHttp);
        }

        $correlationId = ($this->generarCorrelacion)();

        return match ($this->cambios->aplicar(new RevocacionDeRol($solicitud, $correlationId, $comando->ip))) {
            Desenlace::APLICADO => ResultadoRevocacion::revocado($correlationId),
            // Otra solicitud la revocó primero: no hay nada que auditar.
            Desenlace::SIN_EFECTO => ResultadoRevocacion::rechazado('MEMBRESIA_NO_ENCONTRADA', 404),
            Desenlace::DUPLICADO, Desenlace::FALLA => ResultadoRevocacion::errorPersistencia(),
        };
    }
}

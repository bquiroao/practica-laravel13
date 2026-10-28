<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\CasosDeUso;

use Rbac\Aplicacion\Comandos\ComandoAsignarRol;
use Rbac\Aplicacion\Resultados\ResultadoAsignacion;
use Rbac\Aplicacion\Servicios\Desenlace;
use Rbac\Aplicacion\Servicios\RegistradorDeCambios;
use Rbac\Dominio\Cambios\AsignacionDeRol;
use Rbac\Dominio\Modelo\SolicitudAsignacion;
use Rbac\Dominio\Servicios\PoliticaAsignacion;

/**
 * CU-01 Asignar rol a usuario.
 *
 * Orquesta tres pasos y nada más: autorizar «roles.asignar», validar con el Dominio
 * y pedir al RegistradorDeCambios que aplique la asignación con su evidencia (R-07).
 */
final class AsignarRol
{
    public const PERMISO_REQUERIDO = 'roles.asignar';

    public function __construct(
        private readonly AutorizarOperacion $autorizar,
        private readonly PoliticaAsignacion $politica,
        private readonly RegistradorDeCambios $cambios,
        private readonly \Closure $generarCorrelacion,
        private readonly \Closure $reloj,
    ) {
    }

    public function ejecutar(ComandoAsignarRol $comando): ResultadoAsignacion
    {
        // R-04: asignar roles también es una operación protegida.
        $autorizacion = $this->autorizar->exigir($comando->adminId, $comando->tenantToken, $comando->hospitalId, self::PERMISO_REQUERIDO, $comando->ip);
        if (!$autorizacion->permitido) {
            return ResultadoAsignacion::denegado($autorizacion->correlationId);
        }

        $solicitud = new SolicitudAsignacion(
            $comando->adminId,
            $comando->hospitalId,
            $comando->usuarioDestinoId,
            $comando->rol,
            $comando->validoDesde,
            $comando->validoHasta,
            ($this->reloj)(),
        );

        $error = $this->politica->evaluar($solicitud); // fail-closed dentro del Dominio
        if ($error !== null) {
            return ResultadoAsignacion::rechazado($error->codigo, $error->codigoHttp);
        }

        $correlationId = ($this->generarCorrelacion)();

        return match ($this->cambios->aplicar(new AsignacionDeRol($solicitud, $correlationId, $comando->ip))) {
            Desenlace::APLICADO => ResultadoAsignacion::creado($correlationId),
            Desenlace::DUPLICADO => ResultadoAsignacion::rechazado('ASIGNACION_DUPLICADA', 409),
            Desenlace::SIN_EFECTO, Desenlace::FALLA => ResultadoAsignacion::errorPersistencia(),
        };
    }
}

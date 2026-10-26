<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\CasosDeUso;

use Rbac\Aplicacion\Comandos\ComandoAsignarRol;
use Rbac\Aplicacion\Comandos\ComandoAutorizar;
use Rbac\Aplicacion\Puertos\Transaccion;
use Rbac\Aplicacion\Resultados\ResultadoAsignacion;
use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Excepciones\MembresiaDuplicada;
use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Dominio\Modelo\SolicitudAsignacion;
use Rbac\Dominio\Puertos\RegistroAuditoria;
use Rbac\Dominio\Puertos\RepositorioMembresias;
use Rbac\Dominio\Servicios\PoliticaAsignacion;

/**
 * CU-01 Asignar rol a usuario.
 *
 * Autoriza «roles.asignar», valida con las reglas del dominio y guarda la
 * membresía y el evento ROL_ASIGNADO en una sola transacción (R-07).
 */
final class AsignarRol
{
    public const PERMISO_REQUERIDO = 'roles.asignar';

    public function __construct(
        private readonly AutorizarOperacion $autorizar,
        private readonly PoliticaAsignacion $politica,
        private readonly RepositorioMembresias $membresias,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly \Closure $generarCorrelacion,
        private readonly \Closure $reloj,
    ) {
    }

    public function ejecutar(ComandoAsignarRol $comando): ResultadoAsignacion
    {
        // R-04: asignar roles también es una operación protegida.
        $autorizacion = $this->autorizar->ejecutar(new ComandoAutorizar(
            $comando->adminId,
            $comando->tenantToken,
            $comando->hospitalId,
            self::PERMISO_REQUERIDO,
            'membresias',
            $comando->ip,
        ));
        if (!$autorizacion->permitido) {
            return ResultadoAsignacion::denegado($autorizacion->correlationId);
        }

        $ahora = ($this->reloj)();
        $solicitud = new SolicitudAsignacion(
            $comando->adminId,
            $comando->hospitalId,
            $comando->usuarioDestinoId,
            $comando->rol,
            $comando->validoDesde,
            $comando->validoHasta,
            $ahora,
        );

        try {
            $error = $this->politica->evaluar($solicitud);
        } catch (ErrorPersistencia) {
            return ResultadoAsignacion::errorPersistencia();
        }
        if ($error !== null) {
            return ResultadoAsignacion::rechazado($error->codigo, $error->codigoHttp);
        }

        $correlationId = ($this->generarCorrelacion)();

        try {
            $this->transaccion->ejecutar(function () use ($comando, $ahora, $correlationId): void {
                $this->membresias->guardar(new Membresia(
                    $comando->usuarioDestinoId,
                    $comando->hospitalId,
                    $comando->rol,
                    'active',
                    $comando->validoDesde,
                    $comando->validoHasta,
                ));
                $this->auditoria->registrar(new EventoAuditoria(
                    EventoAuditoria::ROL_ASIGNADO,
                    $correlationId,
                    $comando->adminId,
                    $comando->hospitalId,
                    [
                        'usuario_destino' => $comando->usuarioDestinoId,
                        'rol' => $comando->rol,
                        'valido_desde' => $comando->validoDesde->format(DATE_ATOM),
                        'valido_hasta' => $comando->validoHasta?->format(DATE_ATOM),
                        'ip' => $comando->ip,
                    ],
                    $ahora,
                ));
            });
        } catch (MembresiaDuplicada) {
            // Dos solicitudes simultáneas pasaron la regla; el índice único de la base lo detecta.
            return ResultadoAsignacion::rechazado('ASIGNACION_DUPLICADA', 409);
        } catch (\Throwable) {
            return ResultadoAsignacion::errorPersistencia();
        }

        return ResultadoAsignacion::creado($correlationId);
    }
}

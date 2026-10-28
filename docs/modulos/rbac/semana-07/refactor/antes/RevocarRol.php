<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\CasosDeUso;

use Rbac\Aplicacion\Comandos\ComandoAutorizar;
use Rbac\Aplicacion\Comandos\ComandoRevocarRol;
use Rbac\Aplicacion\Puertos\Transaccion;
use Rbac\Aplicacion\Resultados\ResultadoRevocacion;
use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Modelo\SolicitudRevocacion;
use Rbac\Dominio\Puertos\RegistroAuditoria;
use Rbac\Dominio\Puertos\RepositorioMembresias;
use Rbac\Dominio\Servicios\PoliticaRevocacion;

/**
 * Semana 6 — cambio práctico: revocar un rol.
 *
 * Mismo esqueleto que AsignarRol: autoriza «roles.revocar», valida con las reglas
 * del dominio y revoca + registra ROL_REVOCADO en una sola transacción. Desde ese
 * momento, AutorizarOperacion deniega al usuario con MEMBRESIA_NO_VIGENTE.
 */
final class RevocarRol
{
    public const PERMISO_REQUERIDO = 'roles.revocar';

    public function __construct(
        private readonly AutorizarOperacion $autorizar,
        private readonly PoliticaRevocacion $politica,
        private readonly RepositorioMembresias $membresias,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly \Closure $generarCorrelacion,
        private readonly \Closure $reloj,
    ) {
    }

    public function ejecutar(ComandoRevocarRol $comando): ResultadoRevocacion
    {
        $autorizacion = $this->autorizar->ejecutar(new ComandoAutorizar(
            $comando->adminId,
            $comando->tenantToken,
            $comando->hospitalId,
            self::PERMISO_REQUERIDO,
            'membresias',
            $comando->ip,
        ));
        if (!$autorizacion->permitido) {
            return ResultadoRevocacion::denegado($autorizacion->correlationId);
        }

        $ahora = ($this->reloj)();
        $solicitud = new SolicitudRevocacion(
            $comando->adminId,
            $comando->hospitalId,
            $comando->usuarioDestinoId,
            $comando->rol,
            $comando->motivo,
            $ahora,
        );

        try {
            $error = $this->politica->evaluar($solicitud);
        } catch (ErrorPersistencia) {
            return ResultadoRevocacion::errorPersistencia();
        }
        if ($error !== null) {
            return ResultadoRevocacion::rechazado($error->codigo, $error->codigoHttp);
        }

        $correlationId = ($this->generarCorrelacion)();
        $revocadas = 0;

        try {
            // Revocación y evidencia, o ninguna de las dos (R-07).
            $this->transaccion->ejecutar(function () use ($comando, $ahora, $correlationId, &$revocadas): void {
                $revocadas = $this->membresias->revocar($comando->usuarioDestinoId, $comando->hospitalId, $comando->rol, $ahora);
                if ($revocadas === 0) {
                    return; // otra solicitud la revocó primero: no hay nada que auditar
                }
                $this->auditoria->registrar(new EventoAuditoria(
                    EventoAuditoria::ROL_REVOCADO,
                    $correlationId,
                    $comando->adminId,
                    $comando->hospitalId,
                    [
                        'usuario_destino' => $comando->usuarioDestinoId,
                        'rol' => $comando->rol,
                        'motivo' => trim($comando->motivo),
                        'revocadas' => (string) $revocadas,
                        'ip' => $comando->ip,
                    ],
                    $ahora,
                ));
            });
        } catch (\Throwable) {
            return ResultadoRevocacion::errorPersistencia();
        }

        return $revocadas === 0
            ? ResultadoRevocacion::rechazado('MEMBRESIA_NO_ENCONTRADA', 404)
            : ResultadoRevocacion::revocado($correlationId);
    }
}

<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\CasosDeUso;

use Rbac\Aplicacion\Comandos\ComandoAutorizar;
use Rbac\Aplicacion\Resultados\ResultadoAutorizacion;
use Rbac\Dominio\Modelo\ContextoAutorizacion;
use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Modelo\SolicitudAutorizacion;
use Rbac\Dominio\Puertos\RegistroAuditoria;
use Rbac\Dominio\Puertos\RepositorioMembresias;
use Rbac\Dominio\Servicios\PoliticaAutorizacion;

/**
 * CU-03 Autorizar operación + CU-04 Denegar con trazabilidad.
 *
 * Orquesta: cargar membresías → pedir la decisión al dominio → si se deniega,
 * registrar ACCESO_DENEGADO (con respaldo) y devolver 403 con correlation_id.
 */
final class AutorizarOperacion
{
    public function __construct(
        private readonly PoliticaAutorizacion $politica,
        private readonly RepositorioMembresias $membresias,
        private readonly RegistroAuditoria $auditoria,
        private readonly RegistroAuditoria $auditoriaRespaldo,
        private readonly \Closure $generarCorrelacion,
        private readonly \Closure $reloj,
    ) {
    }

    public function ejecutar(ComandoAutorizar $comando): ResultadoAutorizacion
    {
        $solicitud = new SolicitudAutorizacion(
            $comando->usuarioId,
            $comando->tenantToken,
            $comando->tenantSolicitud,
            $comando->permiso,
            $comando->recurso,
            $comando->ip,
            ($this->reloj)(),
        );

        try {
            $membresias = $this->membresias->membresiasDe($solicitud->usuarioId, $solicitud->tenantSolicitud);
        } catch (\Throwable) {
            // R-05 fail-closed: sin poder leer las membresías no se permite nada.
            return $this->denegar($solicitud, 'ERROR_EVALUACION');
        }

        $motivo = $this->politica->evaluar(new ContextoAutorizacion($solicitud, $membresias));

        return $motivo === null ? ResultadoAutorizacion::permitido() : $this->denegar($solicitud, $motivo);
    }

    private function denegar(SolicitudAutorizacion $solicitud, string $motivo): ResultadoAutorizacion
    {
        $correlationId = ($this->generarCorrelacion)();

        $evento = new EventoAuditoria(
            EventoAuditoria::ACCESO_DENEGADO,
            $correlationId,
            $solicitud->usuarioId,
            $solicitud->tenantSolicitud,
            [
                'permiso' => $solicitud->permiso,
                'recurso' => $solicitud->recurso,
                'motivo' => $motivo,
                'ip' => $solicitud->ip,
            ],
            $solicitud->ahora,
        );

        try {
            $this->auditoria->registrar($evento);
        } catch (\Throwable) {
            // E-07: la bitácora principal falló; se intenta el respaldo y la operación sigue denegada.
            try {
                $this->auditoriaRespaldo->registrar($evento);
            } catch (\Throwable) {
                // Sin respaldo disponible la decisión no cambia.
            }
        }

        return ResultadoAutorizacion::denegado($correlationId);
    }
}

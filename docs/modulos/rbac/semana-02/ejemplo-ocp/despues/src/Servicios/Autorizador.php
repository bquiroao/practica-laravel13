<?php

declare(strict_types=1);

namespace Rbac\Servicios;

use Rbac\Contratos\ReglaAutorizacion;
use Rbac\Contratos\RegistroAuditoria;
use Rbac\Contratos\RepositorioMembresias;
use Rbac\Dominio\ContextoAutorizacion;
use Rbac\Dominio\Decision;
use Rbac\Dominio\EventoAuditoria;
use Rbac\Dominio\SolicitudAutorizacion;

/**
 * CU-03 Autorizar operación y CU-04 Denegar con trazabilidad.
 *
 * Abierto a extensión: recibe las reglas como una lista de ReglaAutorizacion.
 * Cerrado a modificación: agregar, quitar o reordenar reglas no cambia esta clase.
 * No conoce ninguna regla concreta.
 */
final class Autorizador
{
    /** @var list<ReglaAutorizacion> */
    private readonly array $reglas;

    /** @param iterable<ReglaAutorizacion> $reglas */
    public function __construct(
        iterable $reglas,
        private readonly RepositorioMembresias $membresias,
        private readonly RegistroAuditoria $auditoria,
        private readonly RegistroAuditoria $auditoriaRespaldo,
        private readonly \Closure $generarCorrelacion,
    ) {
        $lista = [];
        foreach ($reglas as $regla) {
            $lista[] = $regla;
        }
        $this->reglas = $lista;
    }

    public function autorizar(SolicitudAutorizacion $solicitud): Decision
    {
        // R-05 fail-closed: sin reglas configuradas no se permite nada.
        if ($this->reglas === []) {
            return $this->denegar($solicitud, 'SIN_REGLAS_CONFIGURADAS');
        }

        try {
            $contexto = new ContextoAutorizacion(
                $solicitud,
                $this->membresias->membresiasDe($solicitud->usuarioId, $solicitud->tenantSolicitud),
            );
        } catch (\Throwable) {
            return $this->denegar($solicitud, 'ERROR_EVALUACION');
        }

        foreach ($this->reglas as $regla) {
            try {
                $motivo = $regla->evaluar($contexto);
            } catch (\Throwable) {
                $motivo = 'ERROR_EVALUACION';
            }

            if ($motivo !== null) {
                return $this->denegar($solicitud, $motivo);
            }
        }

        return Decision::permitir();
    }

    /** A-12 a A-14: generar correlation_id, registrar ACCESO_DENEGADO y devolver 403. */
    private function denegar(SolicitudAutorizacion $solicitud, string $motivo): Decision
    {
        $correlationId = ($this->generarCorrelacion)();

        $evento = new EventoAuditoria(
            'ACCESO_DENEGADO',
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
            // E-07: si la bitácora principal falla, se usa el respaldo y la operación sigue denegada.
            try {
                $this->auditoriaRespaldo->registrar($evento);
            } catch (\Throwable) {
                // Sin respaldo disponible la decisión no cambia: fail-closed.
            }
        }

        return Decision::denegar($motivo, $correlationId);
    }
}

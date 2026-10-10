<?php

declare(strict_types=1);

namespace Rbac\Servicios;

use Rbac\Contratos\ReglaAsignacion;
use Rbac\Contratos\RegistroAuditoria;
use Rbac\Contratos\RepositorioMembresias;
use Rbac\Contratos\Transaccion;
use Rbac\Dominio\EventoAuditoria;
use Rbac\Dominio\Membresia;
use Rbac\Dominio\ResultadoAsignacion;
use Rbac\Dominio\SolicitudAsignacion;
use Rbac\Dominio\SolicitudAutorizacion;

/**
 * CU-01 Asignar rol a usuario.
 *
 * Las validaciones llegan como una lista de ReglaAsignacion (abierta a extensión).
 * La asignación y su evento ROL_ASIGNADO se guardan en una sola transacción (R-07).
 */
final class ServicioAsignacionRol
{
    public const PERMISO_REQUERIDO = 'roles.asignar';

    /** @var list<ReglaAsignacion> */
    private readonly array $reglas;

    /** @param iterable<ReglaAsignacion> $reglas */
    public function __construct(
        private readonly Autorizador $autorizador,
        iterable $reglas,
        private readonly RepositorioMembresias $membresias,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly \Closure $generarCorrelacion,
    ) {
        $lista = [];
        foreach ($reglas as $regla) {
            $lista[] = $regla;
        }
        $this->reglas = $lista;
    }

    public function asignar(SolicitudAsignacion $solicitud): ResultadoAsignacion
    {
        // A-02 / R-04: asignar roles también es una operación protegida.
        $decision = $this->autorizador->autorizar(new SolicitudAutorizacion(
            $solicitud->adminId,
            $solicitud->tenantToken,
            $solicitud->hospitalId,
            self::PERMISO_REQUERIDO,
            'membresias',
            $solicitud->ip,
            $solicitud->ahora,
        ));
        if (!$decision->permitido) {
            return ResultadoAsignacion::denegado($decision);
        }

        // D-02 a D-04: la primera regla que falla detiene la asignación.
        foreach ($this->reglas as $regla) {
            $error = $regla->evaluar($solicitud);
            if ($error !== null) {
                return ResultadoAsignacion::rechazado($error);
            }
        }

        $correlationId = ($this->generarCorrelacion)();

        try {
            // A-05 + A-06: membresía y evidencia, o ninguna de las dos (E-04).
            $this->transaccion->ejecutar(function () use ($solicitud, $correlationId): void {
                $this->membresias->guardar(new Membresia(
                    $solicitud->usuarioDestinoId,
                    $solicitud->hospitalId,
                    $solicitud->rol,
                    'active',
                    $solicitud->validoDesde,
                    $solicitud->validoHasta,
                ));
                $this->auditoria->registrar(new EventoAuditoria(
                    'ROL_ASIGNADO',
                    $correlationId,
                    $solicitud->adminId,
                    $solicitud->hospitalId,
                    [
                        'usuario_destino' => $solicitud->usuarioDestinoId,
                        'rol' => $solicitud->rol,
                        'valido_desde' => $solicitud->validoDesde->format(DATE_ATOM),
                        'valido_hasta' => $solicitud->validoHasta?->format(DATE_ATOM),
                        'ip' => $solicitud->ip,
                    ],
                    $solicitud->ahora,
                ));
            });
        } catch (\Throwable) {
            return ResultadoAsignacion::fallaAuditoria();
        }

        return ResultadoAsignacion::creado($correlationId);
    }
}

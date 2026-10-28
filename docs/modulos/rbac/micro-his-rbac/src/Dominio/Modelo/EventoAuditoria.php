<?php

declare(strict_types=1);

namespace Rbac\Dominio\Modelo;

/**
 * Evento inmutable de la bitácora: ROL_ASIGNADO, ROL_REVOCADO o ACCESO_DENEGADO.
 *
 * Semana 7: el formato de cada evento (qué datos lleva) vive aquí, en el Dominio,
 * mediante constructores con nombre. Los casos de uso ya no arman arreglos de datos.
 */
final class EventoAuditoria
{
    public const ROL_ASIGNADO = 'ROL_ASIGNADO';
    public const ROL_REVOCADO = 'ROL_REVOCADO';
    public const ACCESO_DENEGADO = 'ACCESO_DENEGADO';

    /** @param array<string, string|null> $datos */
    public function __construct(
        public readonly string $tipo,
        public readonly string $correlationId,
        public readonly string $actorId,
        public readonly string $hospitalId,
        public readonly array $datos,
        public readonly \DateTimeImmutable $ocurridoEn,
    ) {
    }

    public static function accesoDenegado(SolicitudAutorizacion $solicitud, string $motivo, string $correlationId): self
    {
        return new self(self::ACCESO_DENEGADO, $correlationId, $solicitud->usuarioId, $solicitud->tenantSolicitud, [
            'permiso' => $solicitud->permiso,
            'recurso' => $solicitud->recurso,
            'motivo' => $motivo,
            'ip' => $solicitud->ip,
        ], $solicitud->ahora);
    }

    public static function rolAsignado(SolicitudAsignacion $solicitud, string $correlationId, string $ip): self
    {
        return new self(self::ROL_ASIGNADO, $correlationId, $solicitud->adminId, $solicitud->hospitalId, [
            'usuario_destino' => $solicitud->usuarioDestinoId,
            'rol' => $solicitud->rol,
            'valido_desde' => $solicitud->validoDesde->format(DATE_ATOM),
            'valido_hasta' => $solicitud->validoHasta?->format(DATE_ATOM),
            'ip' => $ip,
        ], $solicitud->ahora);
    }

    public static function rolRevocado(SolicitudRevocacion $solicitud, int $revocadas, string $correlationId, string $ip): self
    {
        return new self(self::ROL_REVOCADO, $correlationId, $solicitud->adminId, $solicitud->hospitalId, [
            'usuario_destino' => $solicitud->usuarioDestinoId,
            'rol' => $solicitud->rol,
            'motivo' => trim($solicitud->motivo),
            'revocadas' => (string) $revocadas,
            'ip' => $ip,
        ], $solicitud->ahora);
    }
}

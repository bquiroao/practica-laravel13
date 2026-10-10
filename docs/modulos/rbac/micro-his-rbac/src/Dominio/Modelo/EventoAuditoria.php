<?php

declare(strict_types=1);

namespace Rbac\Dominio\Modelo;

/** Evento inmutable de la bitácora: ROL_ASIGNADO, ROL_REVOCADO o ACCESO_DENEGADO. */
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
}

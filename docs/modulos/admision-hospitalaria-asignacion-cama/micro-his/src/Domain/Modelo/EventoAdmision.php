<?php
declare(strict_types=1);

namespace Admision\Domain\Modelo;

/** Evento inmutable de trazabilidad (RN-05). */
final class EventoAdmision
{
    /** @param array<string, string|bool> $detalle */
    public function __construct(
        public readonly string $admisionId,
        public readonly TenantId $tenantId,
        public readonly TipoEvento $tipo,
        public readonly Responsable $responsable,
        public readonly \DateTimeImmutable $ocurridoEn,
        public readonly array $detalle,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Pacientes\Dominio;

/** Evento PACIENTE_* de la bitácora (R-09). */
final class EventoAuditoria
{
    /** @param array<string, mixed> $detalle */
    public function __construct(
        public readonly string $accion,
        public readonly string $actor,
        public readonly string $hospital,
        public readonly ?string $paciente,
        public readonly array $detalle,
    ) {
    }
}

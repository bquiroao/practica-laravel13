<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Modelo;

/** Evento PACIENTE_* de la bitácora (R-09). */
final class EventoAuditoria
{
    public const REGISTRADO = 'PACIENTE_REGISTRADO';
    public const BUSCADO = 'PACIENTE_BUSCADO';
    public const CONSULTADO = 'PACIENTE_CONSULTADO';
    public const ACTUALIZADO = 'PACIENTE_ACTUALIZADO';

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

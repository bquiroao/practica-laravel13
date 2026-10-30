<?php
declare(strict_types=1);

namespace AlertasCriticas\Domain;

/** Un paso del historial (RN-10). El historial solo crece; nunca se edita. */
final class EventoHistorial
{
    public function __construct(
        public readonly int $orden,
        public readonly string $evento,
        public readonly string $actor,
        public readonly \DateTimeImmutable $momento,
        public readonly string $detalle = '',
    ) {}
}

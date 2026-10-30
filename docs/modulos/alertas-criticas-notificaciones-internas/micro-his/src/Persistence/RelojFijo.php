<?php
declare(strict_types=1);

namespace AlertasCriticas\Persistence;

use AlertasCriticas\Domain\Reloj;

/** Reloj para pruebas y demostraciones: la hora solo cambia cuando se avanza a mano. */
final class RelojFijo implements Reloj
{
    private \DateTimeImmutable $momento;

    public function __construct(string $momento)
    {
        $this->momento = new \DateTimeImmutable($momento, new \DateTimeZone('America/Guatemala'));
    }

    public function ahora(): \DateTimeImmutable
    {
        return $this->momento;
    }

    public function avanzarMinutos(int $minutos): void
    {
        $this->momento = $this->momento->modify("+$minutos minutes");
    }
}

<?php

declare(strict_types=1);

namespace Auditoria\Cliente;

/** Bandeja local en memoria: para pruebas y procesos de corta vida. */
final class BandejaEnMemoria implements BandejaLocal
{
    /** @var array<string, array<string, mixed>> */
    private array $movimientos = [];

    public function guardar(string $eventId, array $movimiento): void
    {
        $this->movimientos[$eventId] = $movimiento;
    }

    public function pendientes(): array
    {
        return $this->movimientos;
    }

    public function quitar(string $eventId): void
    {
        unset($this->movimientos[$eventId]);
    }
}

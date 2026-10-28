<?php

declare(strict_types=1);

namespace Auditoria\Cliente;

/**
 * Bandeja de salida del EMISOR (patrón outbox): si el servicio de auditoría no responde, el
 * movimiento se guarda aquí y se reenvía después. Como el registro es idempotente por event_id,
 * reenviar nunca duplica: entrega «al menos una vez» + idempotencia = efecto «exactamente una vez».
 */
interface BandejaLocal
{
    /** Guarda (o reemplaza, si ya estaba) el movimiento con ese event_id. */
    public function guardar(string $eventId, array $movimiento): void;

    /** @return array<string, array<string, mixed>> event_id → movimiento, en orden de llegada */
    public function pendientes(): array;

    public function quitar(string $eventId): void;
}

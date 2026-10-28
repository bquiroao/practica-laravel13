<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Modelo;

/**
 * Semana 7: el alcance de tenant de una lectura, decidido en UN solo lugar (R-05).
 * «*» pide la red completa; cualquier otro valor, un hospital. Antes, ConsultarMovimientos y
 * VerDetalleMovimiento repetían esta decisión con un booleano $red.
 */
final class Alcance
{
    public const RED = '*';

    private function __construct(public readonly ?string $hospital)
    {
    }

    /** A partir del tenant que pidió el cliente (X-Tenant-ID), que la Presentación ya autenticó. */
    public static function desdeTenant(string $tenant): self
    {
        return new self($tenant === self::RED ? null : $tenant);
    }

    public function esRed(): bool
    {
        return $this->hospital === null;
    }

    /** El valor con el que se le pregunta a la política de acceso. */
    public function tenant(): string
    {
        return $this->hospital ?? self::RED;
    }

    /** Lo que queda escrito en la meta-auditoría (R-08). */
    public function etiqueta(): string
    {
        return $this->esRed() ? 'red' : 'hospital';
    }

    /** La red ve todo; un hospital, solo lo suyo. */
    public function incluye(Movimiento $m): bool
    {
        return $this->esRed() || $m->hospital === $this->hospital;
    }
}

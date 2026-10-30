<?php

declare(strict_types=1);

namespace Qa\Persistencia\Http;

use Qa\Dominio\Puertos\Reloj;

/**
 * Circuit breaker: tras N fallas seguidas se ABRE y deja de llamar durante una pausa; luego permite UN intento
 * (SEMIABIERTO). No cambia la decisión (con el circuito abierto también es «no disponible», fail-closed):
 * evita que cada resultado espere el límite completo a un almacén que ya sabemos caído.
 */
final class CircuitoInterruptor
{
    public const CERRADO = 'CERRADO';
    public const ABIERTO = 'ABIERTO';
    public const SEMIABIERTO = 'SEMIABIERTO';

    private int $fallasSeguidas = 0;
    private ?\DateTimeImmutable $abiertoDesde = null;

    public function __construct(private readonly Reloj $reloj, private readonly int $umbral = 3, private readonly int $pausaSegundos = 30)
    {
    }

    public function estado(): string
    {
        if ($this->abiertoDesde === null) {
            return self::CERRADO;
        }

        return $this->reloj->ahora()->getTimestamp() - $this->abiertoDesde->getTimestamp() >= $this->pausaSegundos ? self::SEMIABIERTO : self::ABIERTO;
    }

    public function permiteIntentar(): bool
    {
        return $this->estado() !== self::ABIERTO;
    }

    public function exito(): void
    {
        $this->fallasSeguidas = 0;
        $this->abiertoDesde = null;
    }

    public function falla(): void
    {
        $this->fallasSeguidas++;
        if ($this->estado() === self::SEMIABIERTO || $this->fallasSeguidas >= $this->umbral) {
            $this->abiertoDesde = $this->reloj->ahora();
        }
    }
}

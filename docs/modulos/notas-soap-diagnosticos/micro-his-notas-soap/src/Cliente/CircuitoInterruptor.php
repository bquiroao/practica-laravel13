<?php

declare(strict_types=1);

namespace NotasSoap\Cliente;

/**
 * Circuit breaker: después de N fallas seguidas deja de llamar al servidor durante un tiempo.
 * CERRADO → (N fallas) → ABIERTO → (enfriamiento) → SEMIABIERTO → (1 éxito) → CERRADO / (1 falla) → ABIERTO
 */
final class CircuitoInterruptor
{
    public const CERRADO = 'CERRADO';
    public const ABIERTO = 'ABIERTO';
    public const SEMIABIERTO = 'SEMIABIERTO';

    private int $fallasSeguidas = 0;
    private ?float $abiertoDesde = null;

    /** @param \Closure(): float|null $reloj segundos; inyectable para probar sin esperar */
    public function __construct(
        private readonly int $umbral = 3,
        private readonly float $enfriamientoSegundos = 30.0,
        private ?\Closure $reloj = null,
    ) {
        $this->reloj ??= static fn (): float => microtime(true);
    }

    public function estado(): string
    {
        if ($this->abiertoDesde === null) {
            return self::CERRADO;
        }

        return (($this->reloj)() - $this->abiertoDesde) >= $this->enfriamientoSegundos ? self::SEMIABIERTO : self::ABIERTO;
    }

    public function permiteLlamar(): bool
    {
        return $this->estado() !== self::ABIERTO;
    }

    public function registrarExito(): void
    {
        $this->fallasSeguidas = 0;
        $this->abiertoDesde = null;
    }

    public function registrarFalla(): void
    {
        $this->fallasSeguidas++;
        if ($this->estado() === self::SEMIABIERTO || $this->fallasSeguidas >= $this->umbral) {
            $this->abiertoDesde = ($this->reloj)();
        }
    }
}

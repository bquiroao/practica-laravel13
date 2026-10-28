<?php

declare(strict_types=1);

namespace Rbac\Cliente;

/**
 * Circuit breaker: tras N fallas seguidas deja de llamar al servicio durante T segundos
 * (estado ABIERTO). Pasado ese tiempo permite UN intento de prueba (SEMIABIERTO):
 * si funciona se cierra; si falla vuelve a abrirse.
 */
final class CircuitoInterruptor
{
    public const CERRADO = 'CERRADO';
    public const ABIERTO = 'ABIERTO';
    public const SEMIABIERTO = 'SEMIABIERTO';

    private string $estado = self::CERRADO;
    private int $fallasSeguidas = 0;
    private float $abiertoDesde = 0.0;
    private readonly \Closure $reloj;

    /** @param \Closure(): float|null $reloj segundos (inyectable para pruebas) */
    public function __construct(
        private readonly int $umbralFallas = 3,
        private readonly float $segundosAbierto = 30.0,
        ?\Closure $reloj = null,
    ) {
        $this->reloj = $reloj ?? static fn (): float => microtime(true);
    }

    public function permiteIntento(): bool
    {
        if ($this->estado === self::ABIERTO && ($this->reloj)() - $this->abiertoDesde >= $this->segundosAbierto) {
            $this->estado = self::SEMIABIERTO;
        }

        return $this->estado !== self::ABIERTO;
    }

    public function registrarExito(): void
    {
        $this->estado = self::CERRADO;
        $this->fallasSeguidas = 0;
    }

    public function registrarFalla(): void
    {
        $this->fallasSeguidas++;
        if ($this->estado === self::SEMIABIERTO || $this->fallasSeguidas >= $this->umbralFallas) {
            $this->estado = self::ABIERTO;
            $this->abiertoDesde = ($this->reloj)();
        }
    }

    public function estado(): string
    {
        return $this->estado;
    }
}

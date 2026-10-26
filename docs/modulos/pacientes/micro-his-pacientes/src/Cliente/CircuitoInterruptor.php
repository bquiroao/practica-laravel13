<?php

declare(strict_types=1);

namespace Pacientes\Cliente;

/**
 * Circuit breaker: tras N fallas seguidas deja de llamar al servidor durante un tiempo
 * (ABIERTO), luego deja pasar una prueba (SEMIABIERTO) y, si responde, vuelve a CERRADO.
 */
final class CircuitoInterruptor
{
    public const CERRADO = 'CERRADO';
    public const ABIERTO = 'ABIERTO';
    public const SEMIABIERTO = 'SEMIABIERTO';

    private string $estado = self::CERRADO;
    private int $fallasSeguidas = 0;
    private float $abiertoDesde = 0.0;

    /** @param \Closure(): float $reloj segundos (inyectable para las pruebas) */
    public function __construct(
        private readonly int $umbral = 3,
        private readonly float $segundosAbierto = 30.0,
        private readonly ?\Closure $reloj = null,
    ) {
    }

    public function permite(): bool
    {
        if ($this->estado === self::ABIERTO && $this->ahora() - $this->abiertoDesde >= $this->segundosAbierto) {
            $this->estado = self::SEMIABIERTO;
        }

        return $this->estado !== self::ABIERTO;
    }

    public function exito(): void
    {
        $this->estado = self::CERRADO;
        $this->fallasSeguidas = 0;
    }

    public function falla(): void
    {
        $this->fallasSeguidas++;
        if ($this->estado === self::SEMIABIERTO || $this->fallasSeguidas >= $this->umbral) {
            $this->estado = self::ABIERTO;
            $this->abiertoDesde = $this->ahora();
        }
    }

    public function estado(): string
    {
        return $this->estado;
    }

    private function ahora(): float
    {
        return $this->reloj !== null ? ($this->reloj)() : microtime(true);
    }
}

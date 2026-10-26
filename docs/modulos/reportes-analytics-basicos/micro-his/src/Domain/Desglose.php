<?php
declare(strict_types=1);

namespace MicroHis\Domain;

/** Nivel 2. Postcondición: total() es igual al numerador del indicador (RN-07). */
final class Desglose
{
    /** @param array<string,int> $partes */
    public function __construct(public readonly string $dimension, public readonly array $partes)
    {
        foreach ($partes as $parte => $valor) {
            if (!is_int($valor) || $valor < 0) {
                throw new \DomainException("La parte «{$parte}» debe ser un entero no negativo");
            }
        }
    }

    public function total(): int
    {
        return array_sum($this->partes);
    }
}

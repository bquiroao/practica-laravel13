<?php
declare(strict_types=1);

/* DESPUÉS: contrato que CUMPLE LSP. Todos los reportes tienen las mismas precondiciones y
 * postcondiciones, así que el cliente puede usar cualquiera sin saber cuál es. */

/** Única precondición común (RN-02). La restricción del Médico la aplica la política, no el reporte. */
final class Filtros
{
    public function __construct(public readonly string $desde, public readonly string $hasta, public readonly ?int $medicoId = null)
    {
        if ($desde > $hasta) {
            throw new InvalidArgumentException('La fecha inicial no puede ser mayor que la final (RN-02)');
        }
    }
}

/** Nivel 1. Postcondición: si la unidad es '%', el valor está entre 0 y 100. */
final class Indicador
{
    public function __construct(
        public readonly string $nombre, public readonly float $valor, public readonly string $unidad,
        public readonly int $numerador, public readonly array $trazabilidad,   // fórmula, período, fuente (RN-05)
    ) {}
}

/** Nivel 2. Postcondición: total() es igual al numerador del indicador (RN-07). */
final class Desglose
{
    /** @param array<string,int> $partes */
    public function __construct(public readonly string $dimension, public readonly array $partes) {}
    public function total(): int { return array_sum($this->partes); }
}

interface ContratoReporte
{
    public function tipo(): string;
    public function dimensiones(): array;                                 // las DECLARA en lugar de fallar
    public function calcular(Filtros $f): Indicador;
    public function desglosar(Filtros $f, string $dimension): Desglose;   // pre: $dimension en dimensiones()
}

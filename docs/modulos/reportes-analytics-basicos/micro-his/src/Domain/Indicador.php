<?php
declare(strict_types=1);

namespace MicroHis\Domain;

/** Nivel 1. Postcondiciones: un porcentaje va de 0 a 100 y la ficha de trazabilidad está completa (RN-05). */
final class Indicador
{
    private const FICHA = ['formula', 'periodo', 'filtros', 'fuente', 'calculado_en'];

    public function __construct(
        public readonly string $nombre,
        public readonly float $valor,
        public readonly string $unidad,
        public readonly int $numerador,
        public readonly array $trazabilidad,
    ) {
        if ($unidad === '%' && ($valor < 0 || $valor > 100)) {
            throw new \DomainException('Un porcentaje debe estar entre 0 y 100');
        }
        foreach (self::FICHA as $campo) {
            if (!isset($trazabilidad[$campo])) {
                throw new \DomainException("Falta «{$campo}» en la ficha de trazabilidad (RN-05)");
            }
        }
    }
}

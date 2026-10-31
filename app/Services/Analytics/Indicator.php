<?php

namespace App\Services\Analytics;

/**
 * Módulo reportes y analytics básicos: el contrato de la semana 2 (LSP). Todo indicador promete lo mismo:
 * - entrega los casos que forman su numerador, cada uno con su peso (1 en los conteos, días en las estancias);
 * - declara sus dimensiones en lugar de fallar al desglosar;
 * - así la suma del desglose es el numerador y la suma de los registros de una parte es esa parte (RN-07),
 *   porque las tres salen de los mismos casos.
 *
 * Un indicador nuevo es una clase nueva registrada en IndicatorRegistry, sin tocar el servicio ni la pantalla (RNF-05).
 */
abstract class Indicator
{
    /** Porcentaje (0 a 100), cantidad o días. La precisión la decide la pantalla, en un solo lugar. */
    public const UNITS = ['%', 'cantidad', 'dias'];

    abstract public function key(): string;

    abstract public function name(): string;

    /** Área del hospital, para agrupar las tarjetas. */
    abstract public function area(): string;

    abstract public function unit(): string;

    /** Qué mide, en lenguaje simple. */
    abstract public function description(): string;

    abstract public function formula(): string;

    /** Tablas de las que sale el dato (RN-05). */
    abstract public function source(): string;

    /** @return array<string, string> clave => etiqueta. La primera es la del desglose por defecto. */
    abstract public function dimensions(): array;

    /** @return array<string, string> columnas de los registros de origen: solo datos mínimos (RN-09) */
    abstract public function columns(): array;

    /**
     * Casos que forman el numerador.
     *
     * @return list<array{peso: int, dims: array<string, string>, fila: array<string, string|int|null>}>
     */
    abstract public function items(Filters $f): array;

    /** Denominador de una tasa o un promedio; null en los conteos. */
    public function denominator(Filters $f, array $items): ?int
    {
        return null;
    }

    /** 100 en los porcentajes. */
    public function factor(): int
    {
        return $this->unit() === '%' ? 100 : 1;
    }

    /** Qué cuenta el numerador: «citas», «días-cama»… */
    public function numeratorLabel(): string
    {
        return 'casos';
    }

    public function denominatorLabel(): ?string
    {
        return null;
    }

    /**
     * Cuántos casos tiene el período. Si es 0 se informa «Sin datos» (RN-06), nunca un cero inventado:
     * en una tasa manda el denominador (0 defunciones de 10 egresos sí es 0 %).
     */
    public function population(array $items, ?int $denominator): int
    {
        return $denominator ?? count($items);
    }

    /** El Médico puede consultar este indicador, limitado a sus propias citas (RN-08). */
    public function doctorScoped(): bool
    {
        return false;
    }

    /** Las dimensiones administrativas (estado, prioridad) no identifican a nadie y no se agrupan como «Otros» (RN-04). */
    public function groupsSmallCases(string $dimension): bool
    {
        return ! in_array($dimension, ['estado', 'prioridad', 'tipo_cama', 'nivel', 'rol'], true);
    }

    /** Aclaración que acompaña la ficha de trazabilidad. */
    public function note(): ?string
    {
        return null;
    }

    /** Módulos que alimentan el indicador. */
    public function feeds(): array
    {
        return [];
    }

    // Etiquetas comunes de las dimensiones.

    protected static function ageGroup(?int $age): string
    {
        return match (true) {
            $age === null => 'Sin dato',
            $age < 5 => '0 a 4 años',
            $age < 15 => '5 a 14 años',
            $age < 30 => '15 a 29 años',
            $age < 45 => '30 a 44 años',
            $age < 60 => '45 a 59 años',
            default => '60 años o más',
        };
    }
}

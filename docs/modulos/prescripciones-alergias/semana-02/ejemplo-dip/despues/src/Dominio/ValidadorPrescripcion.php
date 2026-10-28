<?php

declare(strict_types=1);

namespace Prescripciones\Dominio;

/** Reglas R-01 (completa) y R-02 (dosis máxima diaria). Pura: el catálogo ya resuelto entra como arreglo. */
final class ValidadorPrescripcion
{
    public const FRECUENCIAS_HORAS = [4, 6, 8, 12, 24];

    /**
     * @param list<array<string, mixed>>      $items
     * @param array<string, Medicamento|null> $catalogo código => medicamento (null si no existe)
     * @return array{campos: list<string>, dosis: list<string>}
     */
    public function validar(array $items, array $catalogo): array
    {
        $campos = [];
        $dosis = [];
        if ($items === []) {
            $campos[] = 'items';
        }
        foreach (array_values($items) as $i => $item) {
            $m = $catalogo[(string) ($item['medicamento'] ?? '')] ?? null;
            if ($m === null || !$m->activo) {
                $campos[] = "items[$i].medicamento";
                continue;
            }
            $cantidad = $item['dosis'] ?? null;
            if (!is_int($cantidad) && !is_float($cantidad) || $cantidad <= 0) {
                $campos[] = "items[$i].dosis";
            }
            if (($item['unidad'] ?? null) !== $m->unidad) {
                $campos[] = "items[$i].unidad";
            }
            $frecuencia = $item['frecuencia_horas'] ?? null;
            if (!in_array($frecuencia, self::FRECUENCIAS_HORAS, true)) {
                $campos[] = "items[$i].frecuencia_horas";
            }
            if (!in_array($item['via'] ?? null, $m->vias, true)) {
                $campos[] = "items[$i].via";
            }
            $dias = $item['dias'] ?? null;
            if (!is_int($dias) || $dias < 1 || $dias > 90) {
                $campos[] = "items[$i].dias";
            }
            if ((is_int($cantidad) || is_float($cantidad)) && $cantidad > 0 && in_array($frecuencia, self::FRECUENCIAS_HORAS, true)
                && $cantidad * (24 / $frecuencia) > $m->dosisMaximaDiaria) {
                $dosis[] = "items[$i]";
            }
        }

        return ['campos' => $campos, 'dosis' => $dosis];
    }
}

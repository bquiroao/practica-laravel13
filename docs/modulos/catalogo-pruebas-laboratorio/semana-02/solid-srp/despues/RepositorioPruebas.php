<?php
declare(strict_types=1);

namespace Despues;

/** Responsabilidad única: guardar y leer versiones. La implementación puede cambiar sin tocar reglas. */
interface RepositorioPruebas
{
    public function ultimaVersion(string $codigo): ?array;

    public function guardar(array $nueva, ?string $cierreAnterior): void;
}

final class RepositorioEnMemoria implements RepositorioPruebas
{
    public array $tabla = [];

    public function ultimaVersion(string $codigo): ?array
    {
        $versiones = array_filter($this->tabla, fn ($f) => $f['code'] === $codigo);
        usort($versiones, fn ($a, $b) => $b['version'] <=> $a['version']);
        return $versiones[0] ?? null;
    }

    public function guardar(array $nueva, ?string $cierreAnterior): void
    {
        foreach ($this->tabla as &$fila) {
            if ($cierreAnterior !== null && $fila['code'] === $nueva['code'] && $fila['version'] === $nueva['version'] - 1) {
                $fila['valid_to'] = $cierreAnterior;
            }
        }
        unset($fila);
        $this->tabla[] = $nueva;
    }
}

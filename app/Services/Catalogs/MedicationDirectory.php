<?php

namespace App\Services\Catalogs;

/**
 * De dónde salen los medicamentos que se pueden recetar. Hoy, de config/nido_catalogos.php (provisional). El módulo
 * catalogo-medicamentos debe implementar esta interfaz sobre su tabla y registrarla en AppServiceProvider.
 *
 * Cada medicamento: code, name, presentation, route, groups (familias para alergias cruzadas, p. ej. ['penicilinas']).
 */
interface MedicationDirectory
{
    /** @return list<array<string, mixed>> */
    public function all(): array;

    /** @return array<string, mixed>|null */
    public function find(string $code): ?array;
}

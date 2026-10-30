<?php

namespace App\Services\Catalogs;

/**
 * De dónde salen las pruebas que se pueden ordenar. Hoy, de config/nido_catalogos.php (provisional). El módulo
 * catalogo-pruebas-laboratorio debe implementar esta interfaz sobre su tabla y registrarla en AppServiceProvider.
 *
 * Cada prueba: code, name, specimen_type, unit, ref_low, ref_high, critical_low, critical_high (los cuatro últimos pueden ser null).
 */
interface LabTestDirectory
{
    /** @return list<array<string, mixed>> */
    public function all(): array;

    /** @return array<string, mixed>|null */
    public function find(string $code): ?array;
}

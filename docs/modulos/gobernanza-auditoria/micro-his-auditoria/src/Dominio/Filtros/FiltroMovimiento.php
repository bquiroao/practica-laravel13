<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Filtros;

use Auditoria\Dominio\Modelo\Movimiento;

/**
 * Punto de extensión 2 (OCP): un filtro de la consulta. Cada filtro sabe qué parámetros
 * reclama, cómo validarlos y cómo aplicarlos, en memoria (coincide) y en SQL (sql).
 * El servicio de consulta no conoce ningún filtro concreto.
 */
interface FiltroMovimiento
{
    /** @return list<string> nombres de los parámetros de la consulta que este filtro reclama */
    public function parametros(): array;

    /** Si es obligatorio, una consulta sin sus parámetros se rechaza (R-06). */
    public function obligatorio(): bool;

    /** @param array<string, string> $p */
    public function activo(array $p): bool;

    /**
     * @param array<string, string> $p
     * @return array<string, string> campo => mensaje; [] = válido
     */
    public function validar(array $p): array;

    /** @param array<string, string> $p */
    public function coincide(Movimiento $m, array $p): bool;

    /**
     * Fragmento WHERE con parámetros con nombre: los VALORES nunca se concatenan (RNF-07).
     *
     * @param array<string, string> $p
     * @return array{0: string, 1: array<string, string>}
     */
    public function sql(array $p): array;
}

<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\Servicios;

use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Puertos\Bitacora;

/**
 * Semana 7: la forma de salida de una lectura, en un solo lugar. Una fila de la lista es el resumen
 * y el detalle es el movimiento completo; los dos avisan si el movimiento fue corregido (semana 6).
 */
final class ProyeccionMovimientos
{
    public function __construct(private readonly Bitacora $bitacora)
    {
    }

    /**
     * @param list<Movimiento> $pagina
     * @return list<array<string, mixed>>
     */
    public function resumenes(array $pagina): array
    {
        // El original sigue en SU fecha, pero avisa que fue corregido y por cuál (una sola lectura para toda la página).
        $corregidos = $this->bitacora->correccionesDe(array_map(static fn (Movimiento $m): int => (int) $m->id, $pagina));

        return array_map(static fn (Movimiento $m): array => $m->resumen() + ['corregido_por' => $corregidos[$m->id] ?? null], $pagina);
    }

    /** @return array<string, mixed> */
    public function completo(Movimiento $m): array
    {
        return $m->completo() + ['corregido_por' => $this->bitacora->correccionDe((int) $m->id)?->id];
    }
}

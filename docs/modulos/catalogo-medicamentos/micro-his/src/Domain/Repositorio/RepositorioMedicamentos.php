<?php
declare(strict_types=1);

namespace Catalogo\Domain\Repositorio;

/**
 * La interfaz Repository del dominio. La implementan los adaptadores de persistencia (PDO e
 * InMemory). Los casos de uso NO la piden completa: piden solo la interfaz de rol que usan (ISP).
 */
interface RepositorioMedicamentos extends BusquedaCatalogo, HistorialCatalogo, ControlEquivalencia, EscrituraCatalogo
{
}

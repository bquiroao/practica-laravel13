<?php
declare(strict_types=1);

namespace Admision\Presentation;

use Admision\Application\AdmitirPacienteYAsignarCama;
use Admision\Application\ConsultarCamasDisponibles;

/** Casos de uso ya armados por el Composition Root (bootstrap.php). La presentación solo usa esto. */
final class Servicios
{
    public function __construct(
        public readonly AdmitirPacienteYAsignarCama $admitir,
        public readonly ConsultarCamasDisponibles $camasDisponibles,
    ) {
    }
}

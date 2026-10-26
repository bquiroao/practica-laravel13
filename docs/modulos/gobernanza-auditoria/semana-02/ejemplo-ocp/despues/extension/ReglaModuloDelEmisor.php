<?php

declare(strict_types=1);

namespace Auditoria\Extension;

use Auditoria\Contratos\ReglaMovimiento;
use Auditoria\Dominio\Rechazo;
use Auditoria\Dominio\SolicitudRegistro;

/**
 * EXTENSIÓN (requerimiento nuevo, sin modificar el núcleo):
 * «Cada cuenta de servicio solo registra movimientos de SU módulo: Pacientes no puede
 * escribir un ROL_ASIGNADO a nombre de RBAC». Una cuenta no listada no registra nada (fail-closed).
 */
final class ReglaModuloDelEmisor implements ReglaMovimiento
{
    /** @param array<string, string> $moduloDe cuenta de servicio => módulo */
    public function __construct(private readonly array $moduloDe)
    {
    }

    public function evaluar(SolicitudRegistro $s): ?Rechazo
    {
        return ($this->moduloDe[$s->emisor] ?? null) === $s->datos['modulo'] ? null : Rechazo::prohibido('MODULO_NO_PERMITIDO');
    }
}

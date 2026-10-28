<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Reglas;

use Auditoria\Dominio\Modelo\Rechazo;
use Auditoria\Dominio\Modelo\SolicitudRegistro;

/**
 * Semana 2 (extensión OCP, ahora parte del módulo):
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
        return ($this->moduloDe[$s->emisor] ?? null) === ($s->datos['modulo'] ?? null) ? null : Rechazo::prohibido('MODULO_NO_PERMITIDO');
    }
}

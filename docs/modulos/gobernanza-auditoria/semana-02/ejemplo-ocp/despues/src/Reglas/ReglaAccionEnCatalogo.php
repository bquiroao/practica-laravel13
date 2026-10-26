<?php

declare(strict_types=1);

namespace Auditoria\Reglas;

use Auditoria\Contratos\ReglaMovimiento;
use Auditoria\Dominio\CatalogoAcciones;
use Auditoria\Dominio\Rechazo;
use Auditoria\Dominio\SolicitudRegistro;

/** R-01: módulo y acción deben estar en el catálogo acordado con los emisores. */
final class ReglaAccionEnCatalogo implements ReglaMovimiento
{
    public function __construct(private readonly CatalogoAcciones $catalogo)
    {
    }

    public function evaluar(SolicitudRegistro $s): ?Rechazo
    {
        return $this->catalogo->admite($s->datos['modulo'], $s->datos['accion'])
            ? null : Rechazo::invalido(['accion' => 'No está en el catálogo del módulo.']);
    }
}

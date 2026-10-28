<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Reglas;

use Auditoria\Dominio\Modelo\CatalogoAcciones;
use Auditoria\Dominio\Modelo\Rechazo;
use Auditoria\Dominio\Modelo\SolicitudRegistro;

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

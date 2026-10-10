<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Autenticacion;

use Rbac\Presentacion\Http\Identidad;
use Rbac\Presentacion\Http\Solicitud;

/** Obtiene la identidad del token. En el HIS lo hace JwtAuth; aquí se reemplaza por un doble. */
interface Autenticador
{
    public function autenticar(Solicitud $solicitud): ?Identidad;
}

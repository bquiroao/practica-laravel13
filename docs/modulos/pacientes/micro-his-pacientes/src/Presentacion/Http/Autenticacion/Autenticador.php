<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Autenticacion;

use Pacientes\Presentacion\Http\Identidad;
use Pacientes\Presentacion\Http\Solicitud;

interface Autenticador
{
    /** null si no hay token o no es válido. */
    public function autenticar(Solicitud $solicitud): ?Identidad;
}

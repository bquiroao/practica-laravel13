<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Autenticacion;

use Qa\Presentacion\Http\Identidad;

interface Autenticador
{
    /** Devuelve null si el token no es válido. */
    public function autenticar(?string $cabeceraAuthorization): ?Identidad;
}

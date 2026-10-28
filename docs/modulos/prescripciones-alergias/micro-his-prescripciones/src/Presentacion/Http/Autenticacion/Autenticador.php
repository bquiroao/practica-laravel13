<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http\Autenticacion;

use Prescripciones\Presentacion\Http\Identidad;

interface Autenticador
{
    /** Devuelve null si el token no es válido. */
    public function autenticar(?string $cabeceraAuthorization): ?Identidad;
}

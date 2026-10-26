<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http\Autenticacion;

use Auditoria\Presentacion\Http\Identidad;

/** En el HIS lo implementa el módulo de autenticación (JWT). */
interface Autenticador
{
    public function identificar(string $token): ?Identidad;
}

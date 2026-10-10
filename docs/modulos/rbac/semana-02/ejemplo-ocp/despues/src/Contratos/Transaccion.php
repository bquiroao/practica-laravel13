<?php

declare(strict_types=1);

namespace Rbac\Contratos;

interface Transaccion
{
    /** Ejecuta el trabajo de forma atómica; si lanza una excepción, revierte todo y la relanza. */
    public function ejecutar(\Closure $trabajo): void;
}

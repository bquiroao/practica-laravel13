<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\Puertos;

use Rbac\Dominio\Excepciones\ErrorPersistencia;

interface Transaccion
{
    /**
     * Ejecuta el trabajo de forma atómica: si lanza, revierte todo y relanza.
     *
     * @throws ErrorPersistencia
     */
    public function ejecutar(\Closure $trabajo): void;
}

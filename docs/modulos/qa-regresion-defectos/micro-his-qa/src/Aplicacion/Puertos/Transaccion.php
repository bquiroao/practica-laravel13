<?php

declare(strict_types=1);

namespace Qa\Aplicacion\Puertos;

use Qa\Dominio\Excepciones\ErrorPersistencia;

/** Todo lo que haga $trabajo se confirma junto o no se confirma (resultado + defecto + traza). */
interface Transaccion
{
    /**
     * @template T
     * @param callable(): T $trabajo
     * @return T
     * @throws ErrorPersistencia
     */
    public function ejecutar(callable $trabajo): mixed;
}

<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\Puertos;

/** Todo o nada (R-10): si la operación lanza, no queda nada escrito. */
interface Transaccion
{
    /**
     * @template T
     * @param callable(): T $operacion
     * @return T
     */
    public function ejecutar(callable $operacion): mixed;
}

<?php

declare(strict_types=1);

namespace NotasSoap\Contratos;

/** R-06: ejecuta el bloque completo o no deja nada. */
interface Transaccion
{
    /**
     * @template T
     * @param callable(): T $bloque
     * @return T
     */
    public function ejecutar(callable $bloque): mixed;
}

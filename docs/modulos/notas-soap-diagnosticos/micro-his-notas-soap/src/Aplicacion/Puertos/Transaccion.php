<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Puertos;

/** R-06: ejecuta el bloque completo o no deja nada guardado. */
interface Transaccion
{
    /**
     * @template T
     * @param callable(): T $trabajo
     * @return T
     */
    public function ejecutar(callable $trabajo): mixed;
}

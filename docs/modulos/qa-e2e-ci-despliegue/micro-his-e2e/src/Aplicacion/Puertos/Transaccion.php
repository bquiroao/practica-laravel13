<?php

declare(strict_types=1);

namespace E2e\Aplicacion\Puertos;

/** Todo o nada: si el trabajo lanza, se deshace lo escrito y la excepción se propaga. */
interface Transaccion
{
    public function ejecutar(callable $trabajo): mixed;
}

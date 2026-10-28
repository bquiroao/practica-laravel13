<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\Puertos;

use Prescripciones\Dominio\Excepciones\ErrorPersistencia;

/** Unidad de trabajo: todo o nada (la prescripción, sus ítems y su traza). */
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

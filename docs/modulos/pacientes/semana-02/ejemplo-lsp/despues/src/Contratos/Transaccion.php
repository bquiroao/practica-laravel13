<?php

declare(strict_types=1);

namespace Pacientes\Contratos;

/** Ejecuta un trabajo de forma atómica: si lanza una excepción, no queda ningún cambio. */
interface Transaccion
{
    /**
     * @template T
     * @param \Closure(): T $trabajo
     * @return T
     */
    public function ejecutar(\Closure $trabajo): mixed;
}

<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\Puertos;

use Pacientes\Dominio\Excepciones\ErrorPersistencia;

/** Unidad de trabajo atómica: si el trabajo lanza una excepción, no queda ningún cambio. */
interface Transaccion
{
    /**
     * @template T
     * @param \Closure(): T $trabajo
     * @return T
     * @throws ErrorPersistencia si no se puede iniciar o confirmar
     */
    public function ejecutar(\Closure $trabajo): mixed;
}

<?php

declare(strict_types=1);

namespace Auditoria\Persistencia;

use Auditoria\Aplicacion\Puertos\Transaccion;
use Auditoria\Dominio\Puertos\Bitacora;

/** Los adaptadores de un modo de persistencia, que siempre van juntos (la transacción es del mismo almacén). */
final class ConjuntoAdaptadores
{
    public function __construct(
        public readonly Bitacora $bitacora,
        public readonly Transaccion $transaccion,
    ) {
    }
}

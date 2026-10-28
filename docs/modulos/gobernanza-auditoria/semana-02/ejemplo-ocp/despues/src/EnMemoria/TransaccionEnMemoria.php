<?php

declare(strict_types=1);

namespace Auditoria\EnMemoria;

use Auditoria\Contratos\Transaccion;

/** Guarda una instantánea de la bitácora y la restaura si la operación lanza. */
final class TransaccionEnMemoria implements Transaccion
{
    public function __construct(private readonly BitacoraEnMemoria $bitacora)
    {
    }

    public function ejecutar(callable $operacion): mixed
    {
        $antes = $this->bitacora->instantanea();
        try {
            return $operacion();
        } catch (\Throwable $e) {
            $this->bitacora->restaurar($antes);
            throw $e;
        }
    }
}

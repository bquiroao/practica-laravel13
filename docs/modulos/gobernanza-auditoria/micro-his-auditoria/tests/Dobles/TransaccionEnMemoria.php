<?php

declare(strict_types=1);

namespace Auditoria\Pruebas\Dobles;

use Auditoria\Aplicacion\Puertos\Transaccion;

/** Guarda una instantánea de la bitácora y la restaura si la operación lanza (como un ROLLBACK). */
final class TransaccionEnMemoria implements Transaccion
{
    public int $confirmadas = 0;
    public int $revertidas = 0;

    public function __construct(private readonly BitacoraEnMemoria $bitacora)
    {
    }

    public function ejecutar(callable $operacion): mixed
    {
        $antes = $this->bitacora->instantanea();
        try {
            $resultado = $operacion();
            $this->confirmadas++;

            return $resultado;
        } catch (\Throwable $e) {
            $this->bitacora->restaurar($antes);
            $this->revertidas++;
            throw $e;
        }
    }
}

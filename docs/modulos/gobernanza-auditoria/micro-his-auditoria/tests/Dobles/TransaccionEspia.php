<?php

declare(strict_types=1);

namespace Auditoria\Pruebas\Dobles;

use Auditoria\Aplicacion\Puertos\Transaccion;

/** Envuelve una Transaccion real y cuenta cuántas se confirmaron y cuántas se revirtieron. */
final class TransaccionEspia implements Transaccion
{
    public int $confirmadas = 0;
    public int $revertidas = 0;

    public function __construct(private readonly Transaccion $real)
    {
    }

    public function ejecutar(callable $operacion): mixed
    {
        try {
            $resultado = $this->real->ejecutar($operacion);
            $this->confirmadas++;

            return $resultado;
        } catch (\Throwable $e) {
            $this->revertidas++;
            throw $e;
        }
    }
}

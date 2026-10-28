<?php

declare(strict_types=1);

namespace Auditoria\Persistencia\EnMemoria;

use Auditoria\Aplicacion\Puertos\Transaccion;

/** Toma una instantánea de los almacenes y la restaura si la operación lanza (como un ROLLBACK). */
final class TransaccionEnMemoria implements Transaccion
{
    /** @param list<Restaurable> $almacenes */
    public function __construct(private readonly array $almacenes)
    {
    }

    public function ejecutar(callable $operacion): mixed
    {
        $antes = array_map(static fn (Restaurable $a): mixed => $a->instantanea(), $this->almacenes);
        try {
            return $operacion();
        } catch (\Throwable $e) {
            foreach ($this->almacenes as $i => $almacen) {
                $almacen->restaurar($antes[$i]);
            }
            throw $e;
        }
    }
}

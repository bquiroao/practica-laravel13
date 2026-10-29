<?php

declare(strict_types=1);

namespace E2e\Persistencia\EnMemoria;

use E2e\Aplicacion\Puertos\Transaccion;

/** Todo o nada en memoria: guarda una instantánea de cada participante y la restaura si el trabajo lanza. */
final class TransaccionEnMemoria implements Transaccion
{
    /** @param list<object> $participantes objetos con instantanea() y restaurar() */
    public function __construct(private readonly array $participantes)
    {
    }

    public function ejecutar(callable $trabajo): mixed
    {
        $antes = array_map(static fn (object $p): mixed => $p->instantanea(), $this->participantes);
        try {
            return $trabajo();
        } catch (\Throwable $e) {
            foreach ($this->participantes as $i => $p) {
                $p->restaurar($antes[$i]);
            }
            throw $e;
        }
    }
}

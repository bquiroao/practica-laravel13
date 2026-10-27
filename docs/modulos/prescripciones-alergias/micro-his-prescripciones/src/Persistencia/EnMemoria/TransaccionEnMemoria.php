<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia\EnMemoria;

use Prescripciones\Aplicacion\Puertos\Transaccion;

/** Todo o nada en memoria: toma una instantánea de cada participante y la restaura si el trabajo falla. */
final class TransaccionEnMemoria implements Transaccion
{
    /** @param list<Restaurable> $participantes */
    public function __construct(private readonly array $participantes)
    {
    }

    public function ejecutar(callable $trabajo): mixed
    {
        $estados = array_map(static fn (Restaurable $p): mixed => $p->instantanea(), $this->participantes);
        try {
            return $trabajo();
        } catch (\Throwable $e) {
            foreach ($this->participantes as $i => $p) {
                $p->restaurar($estados[$i]);
            }
            throw $e;
        }
    }
}

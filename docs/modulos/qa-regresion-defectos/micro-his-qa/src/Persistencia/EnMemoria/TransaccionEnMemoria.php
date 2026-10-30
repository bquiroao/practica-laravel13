<?php

declare(strict_types=1);

namespace Qa\Persistencia\EnMemoria;

use Qa\Aplicacion\Puertos\Transaccion;

/** Todo o nada en memoria: toma una instantánea de cada participante y la restaura si el trabajo falla. */
final class TransaccionEnMemoria implements Transaccion
{
    public int $confirmadas = 0;
    public int $revertidas = 0;

    /** @param list<Restaurable> $participantes */
    public function __construct(private readonly array $participantes)
    {
    }

    public function ejecutar(callable $trabajo): mixed
    {
        $estados = array_map(static fn (Restaurable $p): mixed => $p->instantanea(), $this->participantes);
        try {
            $resultado = $trabajo();
            $this->confirmadas++;

            return $resultado;
        } catch (\Throwable $e) {
            foreach ($this->participantes as $i => $p) {
                $p->restaurar($estados[$i]);
            }
            $this->revertidas++;
            throw $e;
        }
    }
}

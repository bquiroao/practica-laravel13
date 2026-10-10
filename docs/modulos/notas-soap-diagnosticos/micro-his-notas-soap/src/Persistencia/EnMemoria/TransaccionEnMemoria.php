<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\EnMemoria;

use NotasSoap\Aplicacion\Puertos\Transaccion;

/** BEGIN / COMMIT / ROLLBACK sobre los almacenes en memoria: si el trabajo falla, todos vuelven a su foto. */
final class TransaccionEnMemoria implements Transaccion
{
    /** @param list<Restaurable> $participantes */
    public function __construct(private readonly array $participantes)
    {
    }

    public function ejecutar(callable $trabajo): mixed
    {
        $fotos = array_map(static fn (Restaurable $r): array => $r->instantanea(), $this->participantes);
        try {
            return $trabajo();
        } catch (\Throwable $e) {
            foreach ($this->participantes as $i => $participante) {
                $participante->restaurar($fotos[$i]);
            }
            throw $e;
        }
    }
}

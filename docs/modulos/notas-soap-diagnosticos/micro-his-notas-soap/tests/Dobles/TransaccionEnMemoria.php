<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Aplicacion\Puertos\Transaccion;

/** Simula BEGIN/COMMIT/ROLLBACK sobre los dobles en memoria. */
final class TransaccionEnMemoria implements Transaccion
{
    /** @param list<Restaurable> $participantes */
    public function __construct(private readonly array $participantes)
    {
    }

    public function ejecutar(callable $trabajo): mixed
    {
        $estados = array_map(static fn (Restaurable $r): array => $r->instantanea(), $this->participantes);
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

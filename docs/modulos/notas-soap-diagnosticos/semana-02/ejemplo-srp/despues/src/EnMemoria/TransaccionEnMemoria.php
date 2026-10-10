<?php

declare(strict_types=1);

namespace NotasSoap\EnMemoria;

use NotasSoap\Contratos\Transaccion;

/** Simula BEGIN/COMMIT/ROLLBACK: si el bloque falla, restaura los repositorios. */
final class TransaccionEnMemoria implements Transaccion
{
    /** @param list<Restaurable> $repositorios */
    public function __construct(private readonly array $repositorios)
    {
    }

    public function ejecutar(callable $bloque): mixed
    {
        $estados = array_map(static fn (Restaurable $r): array => $r->instantanea(), $this->repositorios);
        try {
            return $bloque();
        } catch (\Throwable $e) {
            foreach ($this->repositorios as $i => $repositorio) {
                $repositorio->restaurar($estados[$i]);
            }
            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace Pacientes\Persistencia\EnMemoria;

use Pacientes\Aplicacion\Puertos\Transaccion;

/** Simula BEGIN/COMMIT/ROLLBACK sobre los almacenes en memoria que participan. */
final class TransaccionEnMemoria implements Transaccion
{
    /** @param list<Restaurable> $participantes */
    public function __construct(private readonly array $participantes)
    {
    }

    public function ejecutar(\Closure $trabajo): mixed
    {
        $instantaneas = array_map(static fn (Restaurable $p): mixed => $p->instantanea(), $this->participantes);

        try {
            return $trabajo();
        } catch (\Throwable $e) {
            foreach ($this->participantes as $i => $participante) {
                $participante->restaurar($instantaneas[$i]);
            }
            throw $e;
        }
    }
}

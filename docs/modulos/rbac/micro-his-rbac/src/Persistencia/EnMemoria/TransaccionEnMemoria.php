<?php

declare(strict_types=1);

namespace Rbac\Persistencia\EnMemoria;

use Rbac\Aplicacion\Puertos\Transaccion;

/** BEGIN = instantánea de cada almacén; ROLLBACK = volver a ellas si el trabajo falla. */
final class TransaccionEnMemoria implements Transaccion
{
    /** @var list<Restaurable> */
    private readonly array $almacenes;

    public function __construct(Restaurable ...$almacenes)
    {
        $this->almacenes = array_values($almacenes);
    }

    public function ejecutar(\Closure $trabajo): void
    {
        $instantaneas = array_map(static fn (Restaurable $a): mixed => $a->instantanea(), $this->almacenes);

        try {
            $trabajo();
        } catch (\Throwable $e) {
            foreach ($this->almacenes as $i => $almacen) {
                $almacen->restaurar($instantaneas[$i]);
            }
            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace Rbac\Pruebas\Dobles;

use Rbac\Aplicacion\Puertos\Transaccion;

/** Simula BEGIN/COMMIT/ROLLBACK sobre el repositorio de membresías en memoria. */
final class TransaccionEnMemoria implements Transaccion
{
    public function __construct(private readonly RepositorioMembresiasEnMemoria $membresias)
    {
    }

    public function ejecutar(\Closure $trabajo): void
    {
        $antes = $this->membresias->instantanea();

        try {
            $trabajo();
        } catch (\Throwable $e) {
            $this->membresias->restaurar($antes);
            throw $e;
        }
    }
}

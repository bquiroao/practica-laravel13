<?php
declare(strict_types=1);

use MicroHis\Domain\DatosNoDisponibles;
use MicroHis\Domain\Filtros;
use MicroHis\Domain\LectorOcupacion;

/** Test Double: simula que la base de datos se cayó, con un mensaje técnico que NO debe llegar al usuario. */
final class LectorQueFalla implements LectorOcupacion
{
    private const DETALLE_TECNICO = 'SQLSTATE[08006] connection to server at "127.0.0.1", port 55440 failed';

    public function camasHabilitadas(): int
    {
        throw new DatosNoDisponibles(self::DETALLE_TECNICO);
    }

    public function ocupadasPorSala(Filtros $f): array
    {
        throw new DatosNoDisponibles(self::DETALLE_TECNICO);
    }

    public function registrosDeSala(Filtros $f, string $sala): array
    {
        throw new DatosNoDisponibles(self::DETALLE_TECNICO);
    }
}

<?php

declare(strict_types=1);

namespace Pacientes\Persistencia\EnMemoria;

/** Almacén en memoria que TransaccionEnMemoria puede deshacer (simula ROLLBACK). */
interface Restaurable
{
    public function instantanea(): mixed;

    public function restaurar(mixed $instantanea): void;
}

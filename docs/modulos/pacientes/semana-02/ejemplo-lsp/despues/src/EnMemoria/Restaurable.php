<?php

declare(strict_types=1);

namespace Pacientes\EnMemoria;

/** Lo que TransaccionEnMemoria puede deshacer (simula ROLLBACK). */
interface Restaurable
{
    public function instantanea(): mixed;

    public function restaurar(mixed $instantanea): void;
}

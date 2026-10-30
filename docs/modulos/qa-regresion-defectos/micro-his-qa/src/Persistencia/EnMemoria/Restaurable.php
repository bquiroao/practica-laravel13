<?php

declare(strict_types=1);

namespace Qa\Persistencia\EnMemoria;

/** Un almacén en memoria que participa en TransaccionEnMemoria: guarda y restaura su estado (ROLLBACK). */
interface Restaurable
{
    public function instantanea(): mixed;

    public function restaurar(mixed $estado): void;
}

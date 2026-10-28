<?php

declare(strict_types=1);

namespace Auditoria\Persistencia\EnMemoria;

/** Un almacén en memoria que puede volver a un estado anterior (lo usa TransaccionEnMemoria). */
interface Restaurable
{
    public function instantanea(): mixed;

    public function restaurar(mixed $instantanea): void;
}

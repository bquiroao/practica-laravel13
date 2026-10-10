<?php

declare(strict_types=1);

namespace Rbac\Persistencia\EnMemoria;

/** Almacén en memoria que puede tomar una instantánea de su estado y volver a ella (ROLLBACK). */
interface Restaurable
{
    public function instantanea(): mixed;

    public function restaurar(mixed $instantanea): void;
}

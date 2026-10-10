<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\EnMemoria;

/** Lo que TransaccionEnMemoria necesita para deshacer cambios: tomar y devolver una foto del estado. */
interface Restaurable
{
    public function instantanea(): array;

    public function restaurar(array $estado): void;
}

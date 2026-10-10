<?php

declare(strict_types=1);

namespace NotasSoap\EnMemoria;

/** Lo que necesita TransaccionEnMemoria para poder deshacer cambios. */
interface Restaurable
{
    public function instantanea(): array;

    public function restaurar(array $estado): void;
}

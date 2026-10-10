<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

interface Restaurable
{
    public function instantanea(): array;

    public function restaurar(array $estado): void;
}

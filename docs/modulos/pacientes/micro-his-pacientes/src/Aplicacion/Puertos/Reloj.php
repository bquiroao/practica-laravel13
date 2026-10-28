<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\Puertos;

/** Fecha actual, inyectable para que las pruebas no dependan del día en que se ejecutan. */
interface Reloj
{
    public function hoy(): \DateTimeImmutable;
}

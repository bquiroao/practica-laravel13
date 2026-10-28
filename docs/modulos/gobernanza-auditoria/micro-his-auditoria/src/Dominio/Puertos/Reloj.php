<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Puertos;

/** La hora actual, inyectada: las reglas de fecha se prueban con un reloj fijo. */
interface Reloj
{
    public function ahora(): \DateTimeImmutable;
}

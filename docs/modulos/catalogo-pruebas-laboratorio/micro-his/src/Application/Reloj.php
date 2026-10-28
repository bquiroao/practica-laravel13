<?php
declare(strict_types=1);

namespace Lab\Application;

use DateTimeImmutable;

/** Permite fijar "hoy" en las pruebas. */
interface Reloj
{
    public function ahora(): DateTimeImmutable;
}

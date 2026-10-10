<?php

declare(strict_types=1);

namespace NotasSoap\Contratos;

use DateTimeImmutable;

interface Reloj
{
    public function ahora(): DateTimeImmutable;
}

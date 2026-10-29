<?php
declare(strict_types=1);

namespace Alergias\Dominio;

use DateTimeImmutable;

interface Reloj
{
    public function hoy(): DateTimeImmutable;
}

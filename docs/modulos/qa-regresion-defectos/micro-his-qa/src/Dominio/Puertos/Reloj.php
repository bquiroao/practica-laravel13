<?php

declare(strict_types=1);

namespace Qa\Dominio\Puertos;

interface Reloj
{
    public function ahora(): \DateTimeImmutable;
}

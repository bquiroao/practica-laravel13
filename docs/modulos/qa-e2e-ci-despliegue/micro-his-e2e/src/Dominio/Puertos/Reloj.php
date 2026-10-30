<?php

declare(strict_types=1);

namespace E2e\Dominio\Puertos;

interface Reloj
{
    public function ahora(): \DateTimeImmutable;
}

<?php
declare(strict_types=1);

namespace Admision\Application\Puertos;

interface Reloj
{
    public function ahora(): \DateTimeImmutable;
}

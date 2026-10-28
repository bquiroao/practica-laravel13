<?php

declare(strict_types=1);

namespace Prescripciones\Dominio\Puertos;

interface Reloj
{
    public function ahora(): \DateTimeImmutable;
}

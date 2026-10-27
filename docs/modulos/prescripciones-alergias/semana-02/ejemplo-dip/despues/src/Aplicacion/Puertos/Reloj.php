<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\Puertos;

interface Reloj
{
    public function ahora(): \DateTimeImmutable;
}

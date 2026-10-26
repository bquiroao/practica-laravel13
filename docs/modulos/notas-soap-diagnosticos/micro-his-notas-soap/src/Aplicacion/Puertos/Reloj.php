<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Puertos;

interface Reloj
{
    public function ahora(): \DateTimeImmutable;
}

<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Aplicacion\Puertos\Reloj;

final class RelojFijo implements Reloj
{
    public function __construct(private readonly \DateTimeImmutable $momento = new \DateTimeImmutable('2026-10-12T09:30:00-06:00'))
    {
    }

    public function ahora(): \DateTimeImmutable
    {
        return $this->momento;
    }
}

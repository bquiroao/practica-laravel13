<?php

declare(strict_types=1);

namespace Auditoria\Pruebas\Dobles;

use Auditoria\Dominio\Puertos\Reloj;

final class RelojFijo implements Reloj
{
    public function __construct(private readonly string $ahora = '2026-10-21T12:00:00-06:00')
    {
    }

    public function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->ahora);
    }
}

<?php

declare(strict_types=1);

namespace Auditoria\EnMemoria;

use Auditoria\Contratos\Reloj;

final class RelojFijo implements Reloj
{
    public function __construct(private readonly \DateTimeImmutable $ahora)
    {
    }

    public function ahora(): \DateTimeImmutable
    {
        return $this->ahora;
    }
}

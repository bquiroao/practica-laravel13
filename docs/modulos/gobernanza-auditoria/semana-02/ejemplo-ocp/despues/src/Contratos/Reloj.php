<?php

declare(strict_types=1);

namespace Auditoria\Contratos;

interface Reloj
{
    public function ahora(): \DateTimeImmutable;
}

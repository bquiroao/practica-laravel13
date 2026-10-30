<?php
declare(strict_types=1);

namespace AlertasCriticas\Persistence;

use AlertasCriticas\Domain\Reloj;

final class RelojSistema implements Reloj
{
    public function __construct(private string $zona = 'America/Guatemala') {}

    public function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone($this->zona));
    }
}

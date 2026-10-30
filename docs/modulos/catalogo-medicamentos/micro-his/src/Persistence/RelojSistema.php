<?php
declare(strict_types=1);

namespace Catalogo\Persistence;

use Catalogo\Domain\Puertos\Reloj;

/** "Hoy" según la zona horaria del hospital (America/Guatemala por defecto). */
final class RelojSistema implements Reloj
{
    public function __construct(private readonly string $zona = 'America/Guatemala')
    {
    }

    public function hoy(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone($this->zona));
    }
}

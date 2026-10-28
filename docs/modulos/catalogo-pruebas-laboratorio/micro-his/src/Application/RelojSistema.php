<?php
declare(strict_types=1);

namespace Lab\Application;

use DateTimeImmutable;

final class RelojSistema implements Reloj
{
    public function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}

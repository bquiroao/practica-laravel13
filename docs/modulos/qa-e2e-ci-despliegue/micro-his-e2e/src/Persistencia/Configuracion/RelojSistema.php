<?php

declare(strict_types=1);

namespace E2e\Persistencia\Configuracion;

use E2e\Dominio\Puertos\Reloj;

final class RelojSistema implements Reloj
{
    public function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

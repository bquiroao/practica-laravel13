<?php

declare(strict_types=1);

namespace Auditoria\Persistencia\Configuracion;

use Auditoria\Dominio\Puertos\Reloj;

final class RelojSistema implements Reloj
{
    public function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

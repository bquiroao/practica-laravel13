<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia\Configuracion;

use Prescripciones\Dominio\Puertos\Reloj;

final class RelojSistema implements Reloj
{
    public function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

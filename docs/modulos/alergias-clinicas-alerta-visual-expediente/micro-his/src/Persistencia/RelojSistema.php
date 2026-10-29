<?php
declare(strict_types=1);

namespace Alergias\Persistencia;

use Alergias\Dominio\Reloj;
use DateTimeImmutable;
use DateTimeZone;

final class RelojSistema implements Reloj
{
    public function hoy(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', new DateTimeZone('America/Guatemala'));
    }
}

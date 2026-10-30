<?php

declare(strict_types=1);

namespace Pacientes\Persistencia\Configuracion;

use Pacientes\Aplicacion\Puertos\Reloj;

final class RelojSistema implements Reloj
{
    public function hoy(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', new \DateTimeZone('America/Guatemala'));
    }
}

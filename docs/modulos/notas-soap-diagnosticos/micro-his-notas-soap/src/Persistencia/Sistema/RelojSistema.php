<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\Sistema;

use NotasSoap\Aplicacion\Puertos\Reloj;

/** Adaptador de infraestructura: hora real del servidor en la zona del hospital. */
final class RelojSistema implements Reloj
{
    public function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('America/Guatemala'));
    }
}

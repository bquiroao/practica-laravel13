<?php

declare(strict_types=1);

namespace E2e\Persistencia\Configuracion;

use E2e\Dominio\Puertos\Reloj;

/** Para pruebas y demos: la hora la fija quien lo crea, y se puede avanzar. */
final class RelojFijo implements Reloj
{
    public function __construct(private \DateTimeImmutable $hora)
    {
    }

    public function ahora(): \DateTimeImmutable
    {
        return $this->hora;
    }

    public function avanzar(string $intervalo): void
    {
        $this->hora = $this->hora->modify($intervalo);
    }
}

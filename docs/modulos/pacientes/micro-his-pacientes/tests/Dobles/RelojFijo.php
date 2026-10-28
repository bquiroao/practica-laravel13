<?php

declare(strict_types=1);

namespace Pacientes\Pruebas\Dobles;

use Pacientes\Aplicacion\Puertos\Reloj;

final class RelojFijo implements Reloj
{
    public function __construct(private readonly string $fecha = '2026-10-21')
    {
    }

    public function hoy(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->fecha);
    }
}

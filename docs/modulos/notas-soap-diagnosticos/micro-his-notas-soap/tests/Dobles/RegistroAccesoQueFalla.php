<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Presentacion\Http\Observabilidad\RegistroAcceso;

final class RegistroAccesoQueFalla implements RegistroAcceso
{
    public function registrar(array $linea): void
    {
        throw new \RuntimeException('disco lleno (simulado)');
    }
}

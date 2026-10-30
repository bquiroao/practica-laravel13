<?php

declare(strict_types=1);

namespace Pacientes\Pruebas\Dobles;

use Pacientes\Presentacion\Http\Observabilidad\RegistroAcceso;

final class RegistroAccesoEnMemoria implements RegistroAcceso
{
    /** @var list<array<string, scalar|null>> */
    public array $lineas = [];
    public bool $fallar = false;

    public function registrar(array $linea): void
    {
        if ($this->fallar) {
            throw new \RuntimeException('Simulado: disco lleno');
        }
        $this->lineas[] = $linea;
    }
}

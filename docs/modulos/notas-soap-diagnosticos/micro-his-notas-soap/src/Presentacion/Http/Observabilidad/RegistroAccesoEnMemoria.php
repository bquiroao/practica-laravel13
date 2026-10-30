<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http\Observabilidad;

/** Guarda las líneas en un arreglo (pruebas y demostración). */
final class RegistroAccesoEnMemoria implements RegistroAcceso
{
    /** @var list<array<string, scalar|null>> */
    private array $lineas = [];

    public function registrar(array $linea): void
    {
        $this->lineas[] = $linea;
    }

    /** @return list<array<string, scalar|null>> */
    public function lineas(): array
    {
        return $this->lineas;
    }
}

<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Observabilidad;

/** Una línea estructurada por petición. Nunca recibe tokens, cuerpos ni la query string. */
interface RegistroAcceso
{
    /** @param array<string, scalar|null> $linea */
    public function registrar(array $linea): void;
}

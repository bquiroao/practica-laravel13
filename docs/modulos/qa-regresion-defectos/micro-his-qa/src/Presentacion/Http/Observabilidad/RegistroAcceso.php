<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Observabilidad;

/** Una línea por petición. Nunca el token, el cuerpo ni el contenido de una evidencia. */
interface RegistroAcceso
{
    /** @param array{request_id: string, metodo: string, ruta: string, estado: int, codigo: string, duracion_ms: float, usuario: ?string, tenant: ?string} $linea */
    public function registrar(array $linea): void;
}

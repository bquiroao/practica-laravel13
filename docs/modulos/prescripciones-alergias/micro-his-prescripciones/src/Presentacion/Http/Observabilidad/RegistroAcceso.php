<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http\Observabilidad;

/** Una línea por petición. Nunca el token, el cuerpo, el paciente ni los medicamentos. */
interface RegistroAcceso
{
    /** @param array{request_id: string, metodo: string, ruta: string, estado: int, duracion_ms: float, usuario: ?string, tenant: ?string} $linea */
    public function registrar(array $linea): void;
}

<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http\Observabilidad;

/**
 * Una línea estructurada por petición: request_id, método, ruta, estado, duración, usuario, tenant.
 * NUNCA lleva tokens, cuerpos ni texto clínico.
 */
interface RegistroAcceso
{
    /** @param array<string, scalar|null> $linea */
    public function registrar(array $linea): void;
}

<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http\Observabilidad;

/**
 * Registro TÉCNICO de acceso (observabilidad), distinto de la bitácora de negocio: una línea por
 * petición para medir y depurar. Nunca lleva el token, la consulta (los filtros traen ids de
 * usuario) ni el cuerpo.
 */
interface RegistroAcceso
{
    /** @param array{request_id: string, metodo: string, ruta: string, estado: int, duracion_ms: float, actor: ?string, tenant: ?string} $linea */
    public function registrar(array $linea): void;
}

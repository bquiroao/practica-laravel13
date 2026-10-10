<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http;

/** Manejador de una ruta: traduce HTTP ↔ caso de uso. No contiene SQL ni reglas de negocio. */
interface Accion
{
    /** @param array<string, string> $parametros parámetros capturados de la ruta */
    public function ejecutar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta;
}

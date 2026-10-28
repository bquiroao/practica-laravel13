<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http;

use Pacientes\Presentacion\Http\Controladores\PacientesControlador;

/** Tabla de rutas: cada ruta apunta a un método del controlador. */
final class Rutas
{
    /** @return list<Ruta> */
    public static function definir(PacientesControlador $pacientes): array
    {
        $codigo = '(?<codigo>[A-Za-z0-9-]{1,20})';

        return [
            new Ruta('POST', '#^/api/v1/pacientes$#', $pacientes->registrar(...)),
            new Ruta('GET', '#^/api/v1/pacientes$#', $pacientes->buscar(...)),
            new Ruta('GET', '#^/api/v1/pacientes/' . $codigo . '$#', $pacientes->consultar(...)),
            new Ruta('PUT', '#^/api/v1/pacientes/' . $codigo . '$#', $pacientes->actualizar(...)),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http;

use Rbac\Presentacion\Http\Controladores\AsignacionRolControlador;
use Rbac\Presentacion\Http\Controladores\AuditoriaControlador;
use Rbac\Presentacion\Http\Controladores\OperacionClinicaControlador;

/** Tabla de rutas: cada ruta declara el permiso que la protege y el controlador que la atiende. */
final class Rutas
{
    /** @return list<Ruta> */
    public static function definir(
        AsignacionRolControlador $asignaciones,
        AuditoriaControlador $auditoria,
        OperacionClinicaControlador $operaciones,
    ): array {
        return [
            new Ruta('POST', '#^/api/v1/roles/asignaciones$#', 'roles.asignar', $asignaciones->crear(...)),
            new Ruta('GET', '#^/api/v1/auditoria/eventos/(?<correlationId>[A-Za-z0-9-]{1,64})$#', 'auditoria.consultar', $auditoria->mostrar(...)),
            new Ruta('POST', '#^/api/v1/prescripciones$#', 'prescripciones.crear', $operaciones->prescribir(...)),
            new Ruta('POST', '#^/api/v1/signos-vitales$#', 'signos_vitales.registrar', $operaciones->registrarSignosVitales(...)),
        ];
    }
}

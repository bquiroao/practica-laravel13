<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http;

use Rbac\Presentacion\Http\Controladores\AsignacionRolControlador;
use Rbac\Presentacion\Http\Controladores\AuditoriaControlador;
use Rbac\Presentacion\Http\Controladores\AutorizacionControlador;
use Rbac\Presentacion\Http\Controladores\OperacionClinicaControlador;

/**
 * Tabla de rutas: cada ruta declara el permiso que la protege y el controlador que la atiende.
 * Debe coincidir con el contrato api/openapi.yaml (prueba API-01).
 */
final class Rutas
{
    /** @return list<Ruta> */
    public static function definir(
        AsignacionRolControlador $asignaciones,
        AuditoriaControlador $auditoria,
        OperacionClinicaControlador $operaciones,
        AutorizacionControlador $autorizaciones,
    ): array {
        return [
            new Ruta('POST', '#^/api/v1/roles/asignaciones$#', 'roles.asignar', $asignaciones->crear(...)),
            new Ruta('GET', '#^/api/v1/auditoria/eventos/(?<correlationId>[A-Za-z0-9-]{1,64})$#', 'auditoria.consultar', $auditoria->mostrar(...)),
            // Sin permiso propio: este endpoint ES la autorización (protegerlo con otro permiso sería circular).
            new Ruta('POST', '#^/api/v1/autorizaciones/decisiones$#', null, $autorizaciones->decidir(...)),
            new Ruta('POST', '#^/api/v1/prescripciones$#', 'prescripciones.crear', $operaciones->prescribir(...)),
            new Ruta('POST', '#^/api/v1/signos-vitales$#', 'signos_vitales.registrar', $operaciones->registrarSignosVitales(...)),
        ];
    }
}

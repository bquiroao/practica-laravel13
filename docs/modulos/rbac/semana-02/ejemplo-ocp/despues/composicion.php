<?php

declare(strict_types=1);

/*
 * Raíz de composición: el ÚNICO lugar que conoce las clases concretas.
 * Para extender el comportamiento se agrega una línea aquí; el Autorizador y
 * el ServicioAsignacionRol no se tocan.
 */

use Rbac\Contratos\CatalogoPermisos;
use Rbac\Contratos\DirectorioUsuarios;
use Rbac\Contratos\ReglaAsignacion;
use Rbac\Contratos\ReglaAutorizacion;
use Rbac\Contratos\RepositorioMembresias;
use Rbac\Extension\ReglaRedHospitalaria;
use Rbac\Reglas\Asignacion\ReglaNoAutoasignacion;
use Rbac\Reglas\Asignacion\ReglaRolEnCatalogo;
use Rbac\Reglas\Asignacion\ReglaSinMembresiaDuplicada;
use Rbac\Reglas\Asignacion\ReglaUsuarioDestinoActivo;
use Rbac\Reglas\ReglaMembresiaVigente;
use Rbac\Reglas\ReglaRolConcedePermiso;
use Rbac\Reglas\ReglaTenantCoincide;

/** @return list<ReglaAutorizacion> */
function reglasDeAutorizacion(CatalogoPermisos $catalogo): array
{
    return [
        new ReglaTenantCoincide(),
        new ReglaMembresiaVigente(),
        new ReglaRolConcedePermiso($catalogo),
        // Extensión OCP: requerimiento nuevo agregado sin modificar ninguna clase existente.
        new ReglaRedHospitalaria(['prescripciones.crear'], '10.20.0.0/16'),
    ];
}

/** @return list<ReglaAsignacion> */
function reglasDeAsignacion(
    DirectorioUsuarios $usuarios,
    CatalogoPermisos $catalogo,
    RepositorioMembresias $membresias,
): array {
    return [
        new ReglaUsuarioDestinoActivo($usuarios),
        new ReglaRolEnCatalogo($catalogo),
        new ReglaNoAutoasignacion(),
        new ReglaSinMembresiaDuplicada($membresias),
    ];
}

/** UUID v4 para el correlation_id. */
function generarUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

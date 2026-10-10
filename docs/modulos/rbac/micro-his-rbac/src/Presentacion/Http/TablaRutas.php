<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http;

use Rbac\Aplicacion\CasosDeUso\AsignarRol;
use Rbac\Aplicacion\CasosDeUso\ConsultarEventoAuditoria;
use Rbac\Presentacion\Http\Acciones\AsignarRolAccion;
use Rbac\Presentacion\Http\Acciones\ConsultarDenegacionAccion;
use Rbac\Presentacion\Http\Acciones\OperacionClinicaDemoAccion;

/** Tabla de rutas: cada ruta declara el permiso que la protege. */
final class TablaRutas
{
    /** @return list<Ruta> */
    public static function crear(AsignarRol $asignarRol, ConsultarEventoAuditoria $consultar): array
    {
        return [
            new Ruta('POST', '#^/api/v1/roles/asignaciones$#', 'roles.asignar', new AsignarRolAccion($asignarRol)),
            new Ruta('GET', '#^/api/v1/auditoria/eventos/(?<correlationId>[A-Za-z0-9-]{1,64})$#', 'auditoria.consultar', new ConsultarDenegacionAccion($consultar)),
            new Ruta('POST', '#^/api/v1/prescripciones$#', 'prescripciones.crear', new OperacionClinicaDemoAccion('Prescripción')),
            new Ruta('POST', '#^/api/v1/signos-vitales$#', 'signos_vitales.registrar', new OperacionClinicaDemoAccion('Toma de signos vitales')),
        ];
    }
}

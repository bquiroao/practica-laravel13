<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Controladores;

use Rbac\Aplicacion\CasosDeUso\ConsultarEventoAuditoria;
use Rbac\Presentacion\Http\Peticion;
use Rbac\Presentacion\Http\Respuesta;
use Rbac\Presentacion\Http\Vistas\EventoAuditoriaVista;

/** GET /api/v1/auditoria/eventos/{correlationId} — CU-06, solo eventos del hospital de la petición. */
final class AuditoriaControlador
{
    public function __construct(private readonly ConsultarEventoAuditoria $consultar)
    {
    }

    public function mostrar(Peticion $peticion): Respuesta
    {
        return EventoAuditoriaVista::evento(
            $this->consultar->ejecutar($peticion->parametro('correlationId'), $peticion->tenant()),
        );
    }
}

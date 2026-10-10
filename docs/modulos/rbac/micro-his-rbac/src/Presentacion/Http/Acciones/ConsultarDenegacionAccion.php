<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Acciones;

use Rbac\Aplicacion\CasosDeUso\ConsultarEventoAuditoria;
use Rbac\Presentacion\Http\Accion;
use Rbac\Presentacion\Http\Identidad;
use Rbac\Presentacion\Http\Respuesta;
use Rbac\Presentacion\Http\Solicitud;

/** GET /api/v1/auditoria/eventos/{correlationId} — CU-06, solo eventos del hospital del token. */
final class ConsultarDenegacionAccion implements Accion
{
    public function __construct(private readonly ConsultarEventoAuditoria $consultar)
    {
    }

    public function ejecutar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        $evento = $this->consultar->ejecutar($parametros['correlationId'] ?? '', $solicitud->cabecera('X-Tenant-ID'));

        return $evento === null
            ? Respuesta::json(404, ['mensaje' => 'Evento no encontrado.'])
            : Respuesta::json(200, $evento);
    }
}

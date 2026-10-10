<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http;

use Rbac\Aplicacion\CasosDeUso\AutorizarOperacion;
use Rbac\Aplicacion\Comandos\ComandoAutorizar;
use Rbac\Aplicacion\Resultados\ResultadoAutorizacion;

/** Protección de rutas: antes de ejecutar una acción, pide la autorización del permiso que declara la ruta. */
final class MiddlewareAutorizacion
{
    public function __construct(private readonly AutorizarOperacion $autorizar)
    {
    }

    public function verificar(Solicitud $solicitud, Identidad $identidad, string $permiso): ResultadoAutorizacion
    {
        return $this->autorizar->ejecutar(new ComandoAutorizar(
            $identidad->usuarioId,
            $identidad->tenantToken,
            $solicitud->cabecera('X-Tenant-ID'),
            $permiso,
            $solicitud->metodo . ' ' . $solicitud->ruta,
            $solicitud->ip,
        ));
    }
}

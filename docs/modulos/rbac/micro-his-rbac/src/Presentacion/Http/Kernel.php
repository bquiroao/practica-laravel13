<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http;

use Rbac\Presentacion\Http\Autenticacion\Autenticador;
use Rbac\Presentacion\Http\Vistas\ErrorVista;

/**
 * Front controller: resuelve la ruta y aplica la cadena
 * tenant (400) → autenticación (401) → autorización (403) → controlador.
 */
final class Kernel
{
    /** @param list<Ruta> $rutas */
    public function __construct(
        private readonly array $rutas,
        private readonly Autenticador $autenticador,
        private readonly MiddlewareAutorizacion $autorizacion,
    ) {
    }

    public function manejar(Solicitud $solicitud): Respuesta
    {
        try {
            $encontrada = $this->resolver($solicitud);
            if ($encontrada instanceof Respuesta) {
                return $encontrada;
            }
            [$ruta, $parametros] = $encontrada;

            // D-05 / E-05: sin hospital no se evalúan permisos.
            if ($solicitud->cabecera('X-Tenant-ID') === '') {
                return ErrorVista::sinTenant();
            }

            // D-06 / E-06: sin identidad no hay a quién autorizar.
            $identidad = $this->autenticador->autenticar($solicitud);
            if ($identidad === null) {
                return ErrorVista::noAutenticado();
            }

            // CU-03 / CU-04: protección de la ruta por su permiso declarado.
            $autorizacion = $this->autorizacion->verificar($solicitud, $identidad, $ruta->permiso);
            if (!$autorizacion->permitido) {
                return ErrorVista::accesoDenegado($autorizacion->correlationId);
            }

            $respuesta = ($ruta->destino)(new Peticion($solicitud, $identidad, $parametros));

            return $respuesta instanceof Respuesta ? $respuesta : ErrorVista::errorInterno();
        } catch (\Throwable) {
            return ErrorVista::errorInterno();
        }
    }

    /** @return array{0: Ruta, 1: array<string, string>}|Respuesta */
    private function resolver(Solicitud $solicitud): array|Respuesta
    {
        $metodoNoPermitido = false;
        foreach ($this->rutas as $ruta) {
            $parametros = $ruta->coincide($solicitud->ruta);
            if ($parametros === null) {
                continue;
            }
            if ($ruta->metodo === $solicitud->metodo) {
                return [$ruta, $parametros];
            }
            $metodoNoPermitido = true;
        }

        return $metodoNoPermitido ? ErrorVista::metodoNoPermitido() : ErrorVista::rutaNoEncontrada();
    }
}

<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http;

use Rbac\Presentacion\Http\Autenticacion\Autenticador;

/**
 * Front controller: resuelve la ruta y aplica la cadena
 * tenant (400) → autenticación (401) → autorización (403) → acción.
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
                return Respuesta::json(400, ['mensaje' => 'La cabecera X-Tenant-ID es obligatoria.']);
            }

            // D-06 / E-06: sin identidad no hay a quién autorizar.
            $identidad = $this->autenticador->autenticar($solicitud);
            if ($identidad === null) {
                return Respuesta::json(401, ['mensaje' => 'Token inválido o expirado.']);
            }

            // CU-03 / CU-04: protección de la ruta por su permiso declarado.
            $autorizacion = $this->autorizacion->verificar($solicitud, $identidad, $ruta->permiso);
            if (!$autorizacion->permitido) {
                return Respuesta::json(403, [
                    'mensaje' => 'Acceso denegado.',
                    'correlation_id' => $autorizacion->correlationId,
                ]);
            }

            return $ruta->accion->ejecutar($solicitud, $identidad, $parametros);
        } catch (\Throwable) {
            return Respuesta::json(500, ['mensaje' => 'Error interno.']);
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

        return $metodoNoPermitido
            ? Respuesta::json(405, ['mensaje' => 'Método no permitido.'])
            : Respuesta::json(404, ['mensaje' => 'Ruta no encontrada.']);
    }
}

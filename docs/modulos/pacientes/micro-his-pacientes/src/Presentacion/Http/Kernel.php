<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http;

use Pacientes\Presentacion\Http\Autenticacion\Autenticador;

/**
 * Front controller: resuelve la ruta y aplica la cadena
 * hospital (400) → identidad (401) → token del mismo hospital (403) → acción.
 * Los permisos por operación los decide la Aplicación (R-05).
 */
final class Kernel
{
    /** @param list<Ruta> $rutas */
    public function __construct(
        private readonly array $rutas,
        private readonly Autenticador $autenticador,
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

            // D-01 / E-01: sin hospital no se continúa.
            $hospital = $solicitud->cabecera('X-Tenant-ID');
            if ($hospital === '') {
                return Respuesta::error(400, 'HOSPITAL_REQUERIDO');
            }

            $identidad = $this->autenticador->autenticar($solicitud);
            if ($identidad === null) {
                return Respuesta::error(401, 'NO_AUTENTICADO');
            }
            if ($identidad->hospital !== $hospital) {
                return Respuesta::error(403, 'TENANT_NO_COINCIDE');
            }

            return $ruta->accion->ejecutar($solicitud, $identidad, $parametros);
        } catch (\Throwable) {
            // Nada de trazas ni mensajes internos hacia el cliente.
            return Respuesta::error(500, 'ERROR_INTERNO');
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

        return $metodoNoPermitido ? Respuesta::error(405, 'METODO_NO_PERMITIDO') : Respuesta::error(404, 'RUTA_NO_ENCONTRADA');
    }
}

<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http;

use Auditoria\Presentacion\Http\Autenticacion\Autenticador;

/**
 * Front controller. Orden fijo: ruta (404/405) → hospital (400) → token (401) → tenant del token (403)
 * → acción. Lo inesperado responde 500 genérico, sin trazas ni mensajes internos.
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
            return $this->procesar($solicitud);
        } catch (\Throwable) {
            return TraductorResultado::error(500, 'ERROR_INTERNO');
        }
    }

    private function procesar(Solicitud $solicitud): Respuesta
    {
        $metodoNoPermitido = false;
        foreach ($this->rutas as $ruta) {
            $parametros = $ruta->coincide($solicitud->ruta);
            if ($parametros === null) {
                continue;
            }
            if ($ruta->metodo !== $solicitud->metodo) {
                $metodoNoPermitido = true;
                continue;
            }
            $hospital = $solicitud->cabecera('X-Tenant-ID');
            if ($hospital === '') {
                return TraductorResultado::error(400, 'HOSPITAL_REQUERIDO');
            }
            $token = preg_match('/^Bearer\s+(\S+)$/', $solicitud->cabecera('Authorization'), $m) === 1 ? $m[1] : '';
            $identidad = $token === '' ? null : $this->autenticador->identificar($token);
            if ($identidad === null) {
                return TraductorResultado::error(401, 'NO_AUTENTICADO');
            }
            if (!$identidad->puedeActuarEn($hospital)) {
                return TraductorResultado::error(403, 'TENANT_NO_COINCIDE');
            }

            return ($ruta->accion)($solicitud, $identidad, $parametros);
        }

        return $metodoNoPermitido ? TraductorResultado::error(405, 'METODO_NO_PERMITIDO') : TraductorResultado::error(404, 'RUTA_NO_ENCONTRADA');
    }
}

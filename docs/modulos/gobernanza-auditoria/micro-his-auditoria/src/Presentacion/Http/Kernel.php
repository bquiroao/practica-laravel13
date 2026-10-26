<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http;

use Auditoria\Presentacion\Http\Autenticacion\Autenticador;
use Auditoria\Presentacion\Http\Observabilidad\RegistroAcceso;
use Auditoria\Presentacion\Http\Vistas\ErrorVista;

/**
 * Front controller. Orden fijo: ruta (404/405) → hospital (400) → token (401) → tenant del token (403)
 * → acción. Lo inesperado responde 500 genérico, sin trazas ni mensajes internos.
 * Semana 5: toda respuesta lleva X-Request-ID y deja una línea en el registro de acceso.
 */
final class Kernel
{
    /** @param list<Ruta> $rutas */
    public function __construct(
        private readonly array $rutas,
        private readonly Autenticador $autenticador,
        private readonly ?RegistroAcceso $registroAcceso = null,
    ) {
    }

    public function manejar(Solicitud $solicitud): Respuesta
    {
        $inicio = hrtime(true);
        // Se respeta el id del cliente (para seguir un reintento de punta a punta) si es seguro; si no, se genera.
        $recibido = $solicitud->cabecera('X-Request-ID');
        $requestId = preg_match('/^[A-Za-z0-9-]{8,64}$/', $recibido) === 1 ? $recibido : bin2hex(random_bytes(16));
        $identidad = null;
        try {
            $respuesta = $this->procesar($solicitud, $identidad);
        } catch (\Throwable) {
            $respuesta = ErrorVista::errorInterno();
        }
        $respuesta = $respuesta->conCabecera('X-Request-ID', $requestId);
        try {
            $this->registroAcceso?->registrar([
                'request_id' => $requestId, 'metodo' => $solicitud->metodo, 'ruta' => $solicitud->ruta, 'estado' => $respuesta->estado,
                'duracion_ms' => round((hrtime(true) - $inicio) / 1e6, 3), 'actor' => $identidad?->actor, 'tenant' => $identidad?->hospital,
            ]);
        } catch (\Throwable) {
            // La observabilidad nunca cambia la respuesta al usuario.
        }

        return $respuesta;
    }

    private function procesar(Solicitud $solicitud, ?Identidad &$identidad): Respuesta
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
                return ErrorVista::hospitalRequerido();
            }
            $token = preg_match('/^Bearer\s+(\S+)$/', $solicitud->cabecera('Authorization'), $m) === 1 ? $m[1] : '';
            $identidad = $token === '' ? null : $this->autenticador->identificar($token);
            if ($identidad === null) {
                return ErrorVista::noAutenticado();
            }
            if (!$identidad->puedeActuarEn($hospital)) {
                return ErrorVista::tenantNoCoincide();
            }

            return ($ruta->accion)($solicitud, $identidad, $parametros);
        }

        return $metodoNoPermitido ? ErrorVista::metodoNoPermitido() : ErrorVista::rutaNoEncontrada();
    }
}

<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http;

use Prescripciones\Presentacion\Http\Autenticacion\Autenticador;
use Prescripciones\Presentacion\Http\Observabilidad\RegistroAcceso;

/**
 * Enruta, autentica y exige el tenant antes de llamar a una acción. No conoce SQL ni reglas de negocio.
 * Orden: ruta (404/405) → token (401) → X-Tenant-ID (400/403) → acción. Cualquier excepción: 500 sin detalles.
 * Semana 5: toda respuesta lleva X-Request-ID y cada petición deja una línea en el registro de acceso.
 */
final class Kernel
{
    private const PATRON_REQUEST_ID = '/^[A-Za-z0-9-]{8,64}$/';

    /** @param list<Ruta> $rutas */
    public function __construct(
        private readonly array $rutas,
        private readonly Autenticador $autenticador,
        private readonly ?RegistroAcceso $registro = null,
    ) {
    }

    public function manejar(Solicitud $solicitud): Respuesta
    {
        $inicio = hrtime(true);
        $recibido = $solicitud->cabecera('x-request-id');
        $requestId = $recibido !== null && preg_match(self::PATRON_REQUEST_ID, $recibido) ? $recibido : bin2hex(random_bytes(8));
        $identidad = null;

        try {
            $respuesta = $this->enrutar($solicitud, $identidad);
        } catch (\Throwable) {
            $respuesta = Respuesta::json(500, ['error' => 'ERROR_INTERNO']);
        }
        $respuesta = $respuesta->conCabecera('X-Request-ID', $requestId);

        try {
            $this->registro?->registrar([
                'request_id' => $requestId,
                'metodo' => $solicitud->metodo,
                'ruta' => $solicitud->ruta,
                'estado' => $respuesta->estado,
                'duracion_ms' => round((hrtime(true) - $inicio) / 1e6, 3),
                'usuario' => $identidad?->usuario,
                'tenant' => $identidad?->hospital,
            ]);
        } catch (\Throwable) {
            // El registro de acceso nunca cambia la respuesta (OBS-03).
        }

        return $respuesta;
    }

    private function enrutar(Solicitud $solicitud, ?Identidad &$identidad): Respuesta
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

            return $this->despachar($ruta, $solicitud, $parametros, $identidad);
        }

        return $metodoNoPermitido
            ? Respuesta::json(405, ['error' => 'METODO_NO_PERMITIDO'])
            : Respuesta::json(404, ['error' => 'RUTA_NO_ENCONTRADA']);
    }

    /** @param array<string, string> $parametros */
    private function despachar(Ruta $ruta, Solicitud $solicitud, array $parametros, ?Identidad &$identidad): Respuesta
    {
        $identidad = $this->autenticador->autenticar($solicitud->cabecera('authorization'));
        if ($identidad === null) {
            return Respuesta::json(401, ['error' => 'NO_AUTENTICADO'], ['WWW-Authenticate' => 'Bearer']);
        }
        $tenant = $solicitud->cabecera('x-tenant-id');
        if ($tenant === null || $tenant === '') {
            return Respuesta::json(400, ['error' => 'TENANT_REQUERIDO']);
        }
        if ($tenant !== $identidad->hospital) {
            return Respuesta::json(403, ['error' => 'TENANT_NO_COINCIDE']);
        }

        return ($ruta->manejador)($solicitud, $identidad, $parametros);
    }
}

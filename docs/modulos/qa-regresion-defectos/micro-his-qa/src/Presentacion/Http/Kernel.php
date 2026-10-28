<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http;

use Qa\Presentacion\Http\Autenticacion\Autenticador;

/**
 * Enruta, autentica y exige el tenant antes de llamar a una acción. No conoce SQL ni reglas de negocio.
 * Orden: ruta (404/405) → token (401) → X-Tenant-ID (400/403) → acción. Cualquier excepción: 500 sin detalles.
 * Toda respuesta lleva X-Request-ID (el recibido si es válido, o uno nuevo) para seguir una petición en los registros.
 */
final class Kernel
{
    private const PATRON_REQUEST_ID = '/^[A-Za-z0-9-]{8,64}$/';

    /** @param list<Ruta> $rutas */
    public function __construct(private readonly array $rutas, private readonly Autenticador $autenticador)
    {
    }

    public function manejar(Solicitud $solicitud): Respuesta
    {
        $recibido = $solicitud->cabecera('x-request-id');
        $requestId = $recibido !== null && preg_match(self::PATRON_REQUEST_ID, $recibido) ? $recibido : bin2hex(random_bytes(8));
        try {
            $respuesta = $this->enrutar($solicitud);
        } catch (\Throwable) {
            $respuesta = Respuesta::json(500, ['error' => 'ERROR_INTERNO']);
        }

        return $respuesta->conCabecera('X-Request-ID', $requestId);
    }

    private function enrutar(Solicitud $solicitud): Respuesta
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

            return $this->despachar($ruta, $solicitud, $parametros);
        }

        return $metodoNoPermitido
            ? Respuesta::json(405, ['error' => 'METODO_NO_PERMITIDO'])
            : Respuesta::json(404, ['error' => 'RUTA_NO_ENCONTRADA']);
    }

    /** @param array<string, string> $parametros */
    private function despachar(Ruta $ruta, Solicitud $solicitud, array $parametros): Respuesta
    {
        $identidad = $this->autenticador->autenticar($solicitud->cabecera('authorization'));
        if ($identidad === null) {
            return Respuesta::json(401, ['error' => 'NO_AUTENTICADO'], ['WWW-Authenticate' => 'Bearer']);
        }
        $tenant = $solicitud->cabecera('x-tenant-id');
        if ($tenant === null || $tenant === '') {
            return Respuesta::json(400, ['error' => 'TENANT_REQUERIDO']);
        }
        if ($tenant !== $identidad->tenant) {
            return Respuesta::json(403, ['error' => 'TENANT_NO_COINCIDE']);
        }

        return ($ruta->manejador)($solicitud, $identidad, $parametros);
    }
}

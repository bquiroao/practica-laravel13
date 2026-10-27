<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http;

use Prescripciones\Presentacion\Http\Autenticacion\Autenticador;

/**
 * Enruta, autentica y exige el tenant antes de llamar a una acción. No conoce SQL ni reglas de negocio.
 * Orden: ruta (404/405) → token (401) → X-Tenant-ID (400/403) → acción. Cualquier excepción: 500 sin detalles.
 */
final class Kernel
{
    /** @param list<Ruta> $rutas */
    public function __construct(private readonly array $rutas, private readonly Autenticador $autenticador)
    {
    }

    public function manejar(Solicitud $solicitud): Respuesta
    {
        try {
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
        } catch (\Throwable) {
            return Respuesta::json(500, ['error' => 'ERROR_INTERNO']);
        }
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
        if ($tenant !== $identidad->hospital) {
            return Respuesta::json(403, ['error' => 'TENANT_NO_COINCIDE']);
        }

        return ($ruta->manejador)($solicitud, $identidad, $parametros);
    }
}

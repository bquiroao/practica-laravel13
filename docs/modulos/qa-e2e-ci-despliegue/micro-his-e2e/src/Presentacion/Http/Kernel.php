<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http;

use E2e\Aplicacion\Resultados\Salida;

/**
 * Presentación de la semana 3: autentica (token de demostración → usuario y tenant), enruta, decodifica JSON y base64,
 * llama al caso de uso y traduce su Salida a HTTP. No tiene reglas de negocio ni SQL.
 * La semana 4 la reorganiza en MVC (formularios, controladores y vistas).
 */
final class Kernel
{
    private const ESTADOS = [Salida::CREADO => 201, Salida::OK => 200, Salida::INVALIDO => 422, Salida::CONFLICTO => 409, Salida::NO_ENCONTRADO => 404,
        Salida::DENEGADO => 403, Salida::DEMASIADO_GRANDE => 413, Salida::NO_DISPONIBLE => 503];

    /**
     * @param array<string, object>                      $uc
     * @param array<string, array{0: string, 1: string}> $tokens token => [usuario, tenant]
     */
    public function __construct(private readonly array $uc, private readonly array $tokens)
    {
    }

    public function manejar(Solicitud $s): Respuesta
    {
        try {
            $token = preg_replace('/^Bearer\s+/i', '', (string) $s->cabecera('authorization'));
            [$usuario, $tenantDelToken] = $this->tokens[$token] ?? [null, null];
            if ($usuario === null) {
                return new Respuesta(401, ['error' => 'NO_AUTENTICADO'], ['WWW-Authenticate' => 'Bearer']);
            }
            $tenant = (string) $s->cabecera('x-tenant-id');
            if ($tenant === '') {
                return new Respuesta(400, ['error' => 'TENANT_REQUERIDO']);
            }
            if ($tenant !== $tenantDelToken) {
                return new Respuesta(403, ['error' => 'TENANT_AJENO']);
            }
            $cuerpo = $s->cuerpo === '' ? [] : json_decode($s->cuerpo, true);
            if (!is_array($cuerpo)) {
                return new Respuesta(400, ['error' => 'JSON_INVALIDO']);
            }
            $salida = $this->enrutar($s, $usuario, $tenant, $cuerpo);
            if ($salida instanceof Respuesta) {
                return $salida;
            }

            return new Respuesta(self::ESTADOS[$salida->tipo], $salida->tipo === Salida::CREADO || $salida->tipo === Salida::OK
                ? ['codigo' => $salida->codigo] + $salida->datos : ['error' => $salida->codigo] + $salida->datos);
        } catch (\Throwable) {
            return new Respuesta(500, ['error' => 'ERROR_INTERNO']);
        }
    }

    /** @param array<string, mixed> $c */
    private function enrutar(Solicitud $s, string $usuario, string $tenant, array $c): Salida|Respuesta
    {
        $r = $s->ruta;
        $v = '(\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?)';

        return match (true) {
            $s->metodo === 'POST' && $r === '/api/v1/e2e/escenarios' => $this->uc['escenario']->ejecutar($usuario, $tenant, $c),
            $s->metodo === 'POST' && $r === '/api/v1/e2e/versiones' => $this->uc['version']->ejecutar($usuario, $tenant, $c),
            $s->metodo === 'POST' && preg_match("#^/api/v1/e2e/versiones/$v/plan$#", $r, $m) === 1
                => $this->uc['plan']->ejecutar($usuario, $tenant, $m[1], is_array($c['escenarios'] ?? null) ? $c['escenarios'] : []),
            $s->metodo === 'POST' && preg_match("#^/api/v1/e2e/versiones/$v/ejecuciones$#", $r, $m) === 1
                => $this->uc['iniciar']->ejecutar($usuario, $tenant, $m[1], (string) ($c['digest'] ?? ''), (string) ($c['entorno'] ?? '')),
            $s->metodo === 'POST' && preg_match('#^/api/v1/e2e/ejecuciones/(\d+)/resultados$#', $r, $m) === 1 => $this->reportar($s, $usuario, $tenant, (int) $m[1], $c),
            $s->metodo === 'POST' && preg_match('#^/api/v1/e2e/ejecuciones/(\d+)/cierre$#', $r, $m) === 1
                => $this->uc['cerrar']->ejecutar($usuario, $tenant, (int) $m[1], isset($c['abortar']) ? (string) $c['abortar'] : null),
            $s->metodo === 'POST' && preg_match("#^/api/v1/e2e/versiones/$v/gate$#", $r, $m) === 1
                => $this->uc['gate']->ejecutar($usuario, $tenant, $m[1], (int) ($c['ejecucion'] ?? 0)),
            $s->metodo === 'GET' && preg_match("#^/api/v1/e2e/versiones/$v/trazabilidad$#", $r, $m) === 1 => $this->uc['trazabilidad']->ejecutar($usuario, $tenant, $m[1]),
            default => new Respuesta(404, ['error' => 'RUTA_DESCONOCIDA']),
        };
    }

    /** @param array<string, mixed> $c */
    private function reportar(Solicitud $s, string $usuario, string $tenant, int $id, array $c): Salida|Respuesta
    {
        $clave = (string) $s->cabecera('idempotency-key');
        if ($clave === '') {
            return new Respuesta(400, ['error' => 'IDEMPOTENCY_KEY_REQUERIDA']);
        }
        $artefactos = [];
        foreach (is_array($c['evidencias'] ?? null) ? $c['evidencias'] : [] as $a) {
            $bytes = is_array($a) ? base64_decode((string) ($a['contenido_base64'] ?? ''), true) : false;
            if ($bytes === false) {
                return new Respuesta(400, ['error' => 'BASE64_INVALIDO']);
            }
            $artefactos[] = ['tipo' => (string) ($a['tipo'] ?? ''), 'bytes' => $bytes];
        }

        return $this->uc['reportar']->ejecutar($usuario, $tenant, $id, $clave, ['evidencias' => $artefactos] + $c);
    }
}

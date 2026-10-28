<?php

declare(strict_types=1);

namespace Rbac\Cliente;

/**
 * Cliente que usaría otro módulo del HIS (prescripciones, laboratorio…) para preguntar a RBAC.
 * Solo conoce el contrato HTTP (api/openapi.yaml), no las clases del servidor.
 *
 * Resiliencia: timeout corto, 1 reintento solo ante fallas transitorias (red o 5xx),
 * circuit breaker y fail-closed (cualquier falla = DENEGADO, nunca una excepción).
 */
final class ClienteRbac
{
    private const RUTA = '/api/v1/autorizaciones/decisiones';

    private readonly \Closure $esperar;

    /** @param \Closure(int): void|null $esperar pausa en milisegundos entre reintentos (inyectable) */
    public function __construct(
        private readonly string $urlBase,
        private readonly Transporte $transporte,
        private readonly CircuitoInterruptor $circuito,
        private readonly float $timeoutSegundos = 0.5,
        private readonly int $reintentos = 1,
        ?\Closure $esperar = null,
    ) {
        $this->esperar = $esperar ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    public function verificar(string $token, string $tenant, string $permiso, string $recurso, ?string $requestId = null): DecisionRemota
    {
        if (!$this->circuito->permiteIntento()) {
            return DecisionRemota::denegadaPorFalla(DecisionRemota::ORIGEN_CIRCUITO, 0);
        }

        $cabeceras = [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenant,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if ($requestId !== null) {
            $cabeceras['X-Request-ID'] = $requestId; // misma traza en el módulo cliente y en RBAC
        }
        $cuerpo = (string) json_encode(['permiso' => $permiso, 'recurso' => $recurso]);

        for ($intento = 1; $intento <= 1 + $this->reintentos; $intento++) {
            try {
                $respuesta = $this->transporte->enviar('POST', rtrim($this->urlBase, '/') . self::RUTA, $cabeceras, $cuerpo, $this->timeoutSegundos);
            } catch (FallaTransporte) {
                $respuesta = null; // transitorio: se puede reintentar
            }

            if ($respuesta !== null && $respuesta->estado < 500) {
                $this->circuito->registrarExito(); // el servicio respondió (aunque sea 4xx)
                $datos = $respuesta->json();

                return DecisionRemota::delServicio(
                    $respuesta->estado === 200 && ($datos['decision'] ?? null) === 'PERMITIDO',
                    is_string($datos['correlation_id'] ?? null) ? $datos['correlation_id'] : null,
                    $intento,
                );
            }

            if ($intento <= $this->reintentos) {
                ($this->esperar)(100 * $intento);
            }
        }

        $this->circuito->registrarFalla();

        return DecisionRemota::denegadaPorFalla(DecisionRemota::ORIGEN_FALLA, 1 + $this->reintentos);
    }
}

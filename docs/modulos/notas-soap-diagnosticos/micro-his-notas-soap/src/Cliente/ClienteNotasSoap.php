<?php

declare(strict_types=1);

namespace NotasSoap\Cliente;

/**
 * Librería que usaría otro módulo del HIS (o el backend de la SPA) para hablar con Notas SOAP por red.
 * Se programa contra api/openapi.yaml, no contra las clases del servidor (ARQ-13).
 *
 * Reglas de reintento (máximo 1 reintento, con espera corta):
 *   GET                      se reintenta ante falla de red o 5xx (leer dos veces no cambia nada).
 *   POST /notas-soap         se reintenta CON LA MISMA Idempotency-Key: el servidor no duplica la nota.
 *   POST /notas-soap/{}/firma NO se reintenta solo: si la respuesta se perdió, el cliente consulta la nota.
 *   4xx                      nunca se reintenta: es una respuesta válida del servidor.
 */
final class ClienteNotasSoap
{
    /**
     * @param \Closure(int): void|null $esperar milisegundos; inyectable para que las pruebas no duerman
     * @param \Closure(): string|null $nuevaClave genera Idempotency-Key y X-Request-ID
     */
    public function __construct(
        private readonly string $urlBase,
        private readonly Transporte $transporte,
        private readonly CircuitoInterruptor $circuito,
        private readonly float $timeoutSegundos = 1.0,
        private readonly int $reintentos = 1,
        private ?\Closure $esperar = null,
        private ?\Closure $nuevaClave = null,
    ) {
        $this->esperar ??= static function (int $ms): void {
            usleep($ms * 1000);
        };
        $this->nuevaClave ??= static fn (): string => bin2hex(random_bytes(16));
    }

    /** @param array<string, mixed> $nota cuerpo de la nota (ver RegistroNota en el contrato) */
    public function registrar(string $token, string $hospital, array $nota, ?string $idempotencyKey = null): ResultadoCliente
    {
        $clave = $idempotencyKey ?? ($this->nuevaClave)();

        return $this->llamar('POST', '/api/v1/notas-soap', $token, $hospital, $nota, ['Idempotency-Key' => $clave], true);
    }

    public function firmar(string $token, string $hospital, string $notaId): ResultadoCliente
    {
        return $this->llamar('POST', '/api/v1/notas-soap/' . rawurlencode($notaId) . '/firma', $token, $hospital, null, [], false);
    }

    public function consultar(string $token, string $hospital, string $notaId): ResultadoCliente
    {
        return $this->llamar('GET', '/api/v1/notas-soap/' . rawurlencode($notaId), $token, $hospital, null, [], true);
    }

    public function notasDeExpediente(string $token, string $hospital, string $expedienteId, ?string $estado = null): ResultadoCliente
    {
        $ruta = '/api/v1/expedientes/' . rawurlencode($expedienteId) . '/notas-soap' . ($estado === null ? '' : '?estado=' . rawurlencode($estado));

        return $this->llamar('GET', $ruta, $token, $hospital, null, [], true);
    }

    /**
     * @param array<string, mixed>|null $cuerpo
     * @param array<string, string> $extra
     */
    private function llamar(string $metodo, string $ruta, string $token, string $hospital, ?array $cuerpo, array $extra, bool $reintentable): ResultadoCliente
    {
        $requestId = ($this->nuevaClave)();
        if (!$this->circuito->permiteLlamar()) {
            return new ResultadoCliente(ResultadoCliente::NO_DISPONIBLE, null, null, 'CIRCUITO_ABIERTO', 0, $requestId);
        }
        $cabeceras = ['Authorization' => 'Bearer ' . $token, 'X-Tenant-ID' => $hospital, 'X-Request-ID' => $requestId] + $extra;
        $maximo = $reintentable ? 1 + $this->reintentos : 1;

        for ($intento = 1; ; $intento++) {
            try {
                $r = $this->transporte->enviar($metodo, rtrim($this->urlBase, '/') . $ruta, $cabeceras, $cuerpo, $this->timeoutSegundos);
                if ($r->estado < 500) {
                    $this->circuito->registrarExito();
                    $tipo = $r->estado < 400 ? ResultadoCliente::OK : ResultadoCliente::RECHAZADO;

                    return new ResultadoCliente($tipo, $r->estado, $r->cuerpo, 'SERVIDOR', $intento, $requestId, $r->cabecera('Idempotent-Replayed') === 'true');
                }
                $ultimo = new ResultadoCliente(ResultadoCliente::NO_DISPONIBLE, $r->estado, $r->cuerpo, 'ERROR_SERVIDOR', $intento, $requestId);
            } catch (FallaTransporte) {
                $ultimo = new ResultadoCliente(ResultadoCliente::NO_DISPONIBLE, null, null, 'FALLA_RED', $intento, $requestId);
            }
            $this->circuito->registrarFalla();
            if ($intento >= $maximo || !$this->circuito->permiteLlamar()) {
                return $ultimo;
            }
            ($this->esperar)(100 * $intento);
        }
    }
}

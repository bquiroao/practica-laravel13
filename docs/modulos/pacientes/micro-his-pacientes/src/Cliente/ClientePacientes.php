<?php

declare(strict_types=1);

namespace Pacientes\Cliente;

/**
 * Cliente de la API de Pacientes para OTROS módulos del HIS (admisión, citas...). Se programa
 * contra el contrato (api/openapi.yaml), no contra las clases del servidor (ARQ-01).
 *
 * Política de resiliencia por operación:
 *  - GET  (buscar, consultar): timeout + 1 reintento ante red/5xx + circuit breaker.
 *    consultar() además usa ETag (If-None-Match → 304) y, si el servidor falla, devuelve la
 *    última copia conocida marcada CACHE_DEGRADADA (stale-if-error).
 *  - POST (registrar): SIEMPRE con Idempotency-Key, así el reintento no duplica pacientes.
 *  - PUT  (actualizar): NO se reintenta. Si no hay respuesta, el resultado es INCIERTO y el
 *    consumidor debe recargar el paciente (un reintento ciego daría 409 o pisaría datos).
 */
final class ClientePacientes
{
    /** @var array<string, array{etag: string, cuerpo: array<string, mixed>}> codigo → última copia (solo en memoria) */
    private array $cache = [];

    /**
     * @param \Closure(int): void $dormir milisegundos (inyectable para las pruebas)
     * @param \Closure(): string $generarId X-Request-ID / Idempotency-Key
     */
    public function __construct(
        private readonly Transporte $transporte,
        private readonly CircuitoInterruptor $circuito,
        private readonly string $token,
        private readonly string $hospital,
        private readonly float $timeoutSegundos = 0.5,
        private readonly int $reintentos = 1,
        private readonly int $esperaMs = 100,
        private readonly ?\Closure $dormir = null,
        private readonly ?\Closure $generarId = null,
    ) {
    }

    /** @param array<string, string> $criterios */
    public function buscar(array $criterios, ?string $requestId = null): ResultadoCliente
    {
        return $this->llamar(new PeticionHttp('GET', '/api/v1/pacientes', consulta: $criterios), $requestId ?? $this->nuevoId(), true);
    }

    public function consultar(string $codigo, ?string $requestId = null): ResultadoCliente
    {
        $codigo = strtoupper($codigo);
        $copia = $this->cache[$codigo] ?? null;
        $cabeceras = $copia === null ? [] : ['If-None-Match' => $copia['etag']];
        $r = $this->llamar(new PeticionHttp('GET', '/api/v1/pacientes/' . rawurlencode($codigo), $cabeceras), $requestId ?? $this->nuevoId(), true);

        if ($r->estado === 304 && $copia !== null) {
            return new ResultadoCliente(200, $copia['cuerpo'], ResultadoCliente::CACHE_VALIDADA, $r->requestId, $r->intentos);
        }
        if ($r->origen === ResultadoCliente::SERVIDOR && $r->estado === 200) {
            $this->cache[$codigo] = ['etag' => (string) ($r->cuerpo['__etag'] ?? ''), 'cuerpo' => self::sinMeta($r->cuerpo)];

            return new ResultadoCliente(200, self::sinMeta($r->cuerpo), ResultadoCliente::SERVIDOR, $r->requestId, $r->intentos);
        }
        if ($r->estado === 404) {
            unset($this->cache[$codigo]); // ya no existe (o ya no es de este hospital): no servir copias
        }
        if (!$r->disponible() && $copia !== null) {
            return new ResultadoCliente(200, $copia['cuerpo'], ResultadoCliente::CACHE_DEGRADADA, $r->requestId, $r->intentos);
        }

        return $r;
    }

    /** @param array<string, mixed> $paciente cuerpo JSON de la API */
    public function registrar(array $paciente, ?string $claveIdempotencia = null, ?string $requestId = null): ResultadoCliente
    {
        $peticion = new PeticionHttp('POST', '/api/v1/pacientes', ['Idempotency-Key' => $claveIdempotencia ?? $this->nuevoId()], cuerpo: $paciente);

        return $this->llamar($peticion, $requestId ?? $this->nuevoId(), true);
    }

    /** @param array<string, mixed> $paciente */
    public function actualizar(string $codigo, int $versionLeida, array $paciente, ?string $requestId = null): ResultadoCliente
    {
        $peticion = new PeticionHttp('PUT', '/api/v1/pacientes/' . rawurlencode(strtoupper($codigo)), ['If-Match' => '"' . $versionLeida . '"'], cuerpo: $paciente);
        $r = $this->llamar($peticion, $requestId ?? $this->nuevoId(), false);
        if ($r->origen === ResultadoCliente::FALLA_SERVICIO && $r->estado === null) {
            return new ResultadoCliente(null, [], ResultadoCliente::RESULTADO_INCIERTO, $r->requestId, $r->intentos);
        }
        unset($this->cache[strtoupper($codigo)]);

        return $r;
    }

    public function estadoCircuito(): string
    {
        return $this->circuito->estado();
    }

    private function llamar(PeticionHttp $peticion, string $requestId, bool $reintentable): ResultadoCliente
    {
        $cabeceras = $peticion->cabeceras + [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID' => $this->hospital,
            'X-Request-ID' => $requestId, // el MISMO id en todos los reintentos
        ];
        $peticion = new PeticionHttp($peticion->metodo, $peticion->ruta, $cabeceras, $peticion->consulta, $peticion->cuerpo);
        $maximo = $reintentable ? 1 + $this->reintentos : 1;
        $estado = null;

        for ($intento = 1; $intento <= $maximo; $intento++) {
            if (!$this->circuito->permite()) {
                return new ResultadoCliente(null, [], ResultadoCliente::CIRCUITO_ABIERTO, $requestId, $intento - 1);
            }
            try {
                $r = $this->transporte->enviar($peticion, $this->timeoutSegundos);
                $estado = $r->estado;
                if ($r->estado < 500) {
                    $this->circuito->exito(); // 2xx, 3xx y 4xx: el servicio respondió
                    $cuerpo = $r->cuerpo + ($r->cabecera('ETag') !== '' ? ['__etag' => $r->cabecera('ETag')] : []);

                    return new ResultadoCliente($r->estado, $cuerpo, ResultadoCliente::SERVIDOR, $requestId, $intento);
                }
            } catch (FallaTransporte) {
                $estado = null;
            }
            $this->circuito->falla();
            if ($intento < $maximo) {
                $this->dormir($this->esperaMs * $intento);
            }
        }

        return new ResultadoCliente($estado, [], ResultadoCliente::FALLA_SERVICIO, $requestId, $maximo);
    }

    /** @param array<string, mixed> $cuerpo */
    private static function sinMeta(array $cuerpo): array
    {
        unset($cuerpo['__etag']);

        return $cuerpo;
    }

    private function dormir(int $milisegundos): void
    {
        $this->dormir !== null ? ($this->dormir)($milisegundos) : usleep($milisegundos * 1000);
    }

    private function nuevoId(): string
    {
        return $this->generarId !== null ? ($this->generarId)() : bin2hex(random_bytes(16));
    }
}

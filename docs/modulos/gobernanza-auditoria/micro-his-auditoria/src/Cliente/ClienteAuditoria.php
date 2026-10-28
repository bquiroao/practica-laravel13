<?php

declare(strict_types=1);

namespace Auditoria\Cliente;

/**
 * Cliente de la API de Auditoría para los módulos EMISORES (Pacientes, RBAC, Citas…) y para
 * herramientas de consulta. Se programa contra el contrato (api/openapi.yaml), no contra las
 * clases del servidor (ARQ-01).
 *
 * Política de resiliencia:
 *  - registrar(): el event_id lo pone el emisor y no cambia entre intentos, así que el POST es
 *    idempotente y se REINTENTA. Si aun así no hay servicio (red, 5xx o circuito abierto), el
 *    movimiento va a la BandejaLocal y la operación del emisor NO falla: la auditoría no puede
 *    tumbar la atención de un paciente. Un 4xx no se reintenta ni se guarda: es un error del emisor.
 *  - reenviarPendientes(): vacía la bandeja cuando el servicio vuelve.
 *  - consultar(): GET con reintento y circuit breaker; sin caché (la bitácora no se copia fuera).
 */
final class ClienteAuditoria
{
    private const RUTA = '/api/v1/auditoria/movimientos';

    /**
     * @param \Closure(int): void|null $dormir milisegundos (inyectable para las pruebas)
     * @param \Closure(): string|null $generarId X-Request-ID
     */
    public function __construct(
        private readonly Transporte $transporte,
        private readonly CircuitoInterruptor $circuito,
        private readonly BandejaLocal $bandeja,
        private readonly string $token,
        private readonly string $hospital,
        private readonly float $timeoutSegundos = 0.5,
        private readonly int $reintentos = 2,
        private readonly int $esperaMs = 100,
        private readonly ?\Closure $dormir = null,
        private readonly ?\Closure $generarId = null,
    ) {
    }

    /** @param array<string, mixed> $movimiento cuerpo JSON de la API; DEBE traer event_id */
    public function registrar(array $movimiento, ?string $requestId = null): ResultadoCliente
    {
        $eventId = (string) ($movimiento['event_id'] ?? '');
        if ($eventId === '') {
            throw new \InvalidArgumentException('El emisor debe asignar un event_id estable antes de enviar (idempotencia).');
        }
        $r = $this->llamar(new PeticionHttp('POST', self::RUTA, cuerpo: $movimiento), $requestId ?? $this->nuevoId(), $this->reintentos);
        if ($r->origen === ResultadoCliente::SERVIDOR) {
            return $r;
        }
        $this->bandeja->guardar($eventId, $movimiento);

        return new ResultadoCliente(null, ['event_id' => $eventId], ResultadoCliente::EN_BANDEJA_LOCAL, $r->requestId, $r->intentos);
    }

    /**
     * Reenvía la bandeja local en orden; se detiene en la primera falla de servicio.
     *
     * @return array{registrados: int, rechazados: array<string, int>, pendientes: int}
     */
    public function reenviarPendientes(): array
    {
        $registrados = 0;
        $rechazados = [];
        foreach ($this->bandeja->pendientes() as $eventId => $movimiento) {
            $r = $this->llamar(new PeticionHttp('POST', self::RUTA, cuerpo: $movimiento), $this->nuevoId(), 0);
            if ($r->origen !== ResultadoCliente::SERVIDOR) {
                break;
            }
            if ($r->registrado()) {
                $registrados++;
            } else {
                $rechazados[$eventId] = (int) $r->estado;   // un 4xx no se arregla reenviando: se informa y se saca
            }
            $this->bandeja->quitar($eventId);
        }

        return ['registrados' => $registrados, 'rechazados' => $rechazados, 'pendientes' => count($this->bandeja->pendientes())];
    }

    /** @param array<string, string> $filtros desde, hasta, usuario, modulo, accion, resultado, cursor */
    public function consultar(array $filtros, ?string $requestId = null): ResultadoCliente
    {
        return $this->llamar(new PeticionHttp('GET', self::RUTA, consulta: $filtros), $requestId ?? $this->nuevoId(), $this->reintentos);
    }

    public function estadoCircuito(): string
    {
        return $this->circuito->estado();
    }

    private function llamar(PeticionHttp $peticion, string $requestId, int $reintentos): ResultadoCliente
    {
        $peticion = new PeticionHttp($peticion->metodo, $peticion->ruta, $peticion->cabeceras + [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID' => $this->hospital,
            'X-Request-ID' => $requestId,                   // el MISMO id en todos los reintentos
        ], $peticion->consulta, $peticion->cuerpo);
        $maximo = 1 + $reintentos;
        $estado = null;
        for ($intento = 1; $intento <= $maximo; $intento++) {
            if (!$this->circuito->permite()) {
                return new ResultadoCliente(null, [], ResultadoCliente::CIRCUITO_ABIERTO, $requestId, $intento - 1);
            }
            try {
                $r = $this->transporte->enviar($peticion, $this->timeoutSegundos);
                $estado = $r->estado;
                if ($r->estado < 500) {
                    $this->circuito->exito();               // 2xx y 4xx: el servicio respondió
                    return new ResultadoCliente($r->estado, $r->cuerpo, ResultadoCliente::SERVIDOR, $requestId, $intento);
                }
            } catch (FallaTransporte) {
                $estado = null;
            }
            $this->circuito->falla();
            if ($intento < $maximo) {
                $this->dormir($this->esperaMs * $intento);  // espera creciente
            }
        }

        return new ResultadoCliente($estado, [], ResultadoCliente::FALLA_SERVICIO, $requestId, $maximo);
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

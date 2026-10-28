<?php

declare(strict_types=1);

namespace Prescripciones\Cliente;

/**
 * Cliente de la API para la interfaz (Vue) u otros módulos. Se programa contra el CONTRATO (api/openapi.yaml),
 * no contra las clases del servidor (ARQ-10).
 *
 * Política de resiliencia de la EMISIÓN:
 *  - una Idempotency-Key por intención de emitir, la MISMA en todos los reintentos (y un mismo X-Request-ID);
 *  - reintenta solo si no hubo respuesta o fue 5xx; un 4xx es una decisión clínica o de datos, no se reintenta;
 *  - sin bandeja local: una prescripción no puede quedar «pendiente de enviar». Si se agotan los intentos el
 *    resultado es INCIERTO y la interfaz lo dice; reintentar con la misma clave nunca emite dos veces.
 */
final class ClientePrescripciones
{
    private const BASE = '/api/v1/prescripciones';

    /** @var \Closure(int): void */
    private \Closure $esperar;

    /** @param \Closure(int): void|null $esperar espera entre intentos (ms); las pruebas la anulan */
    public function __construct(
        private readonly Transporte $transporte,
        private readonly string $token,
        private readonly string $hospital,
        private readonly int $reintentos = 2,
        ?\Closure $esperar = null,
    ) {
        $this->esperar = $esperar ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    /** @param list<array<string, mixed>> $items */
    public function verificar(string $paciente, array $items): ResultadoCliente
    {
        return $this->llamar('POST', self::BASE . '/verificaciones', ['paciente' => $paciente, 'items' => $items], null);
    }

    /** @param list<array<string, mixed>> $items @param string|null $clave la de un intento anterior INCIERTO, para resolverlo */
    public function emitir(string $paciente, array $items, ?string $justificacion = null, bool $confirmaSinAlergias = false, ?string $clave = null): ResultadoCliente
    {
        $clave ??= 'emi-' . bin2hex(random_bytes(12));

        return $this->llamar('POST', self::BASE, ['paciente' => $paciente, 'items' => $items, 'justificacion' => $justificacion,
            'confirma_sin_alergias' => $confirmaSinAlergias], $clave);
    }

    public function consultar(int $id): ResultadoCliente
    {
        return $this->llamar('GET', self::BASE . "/$id", null, null);
    }

    /** @param array<string, mixed>|null $cuerpo */
    private function llamar(string $metodo, string $ruta, ?array $cuerpo, ?string $clave): ResultadoCliente
    {
        $requestId = 'cli-' . bin2hex(random_bytes(8));
        $cabeceras = ['Authorization' => "Bearer {$this->token}", 'X-Tenant-ID' => $this->hospital, 'X-Request-ID' => $requestId]
            + ($clave !== null ? ['Idempotency-Key' => $clave] : []);
        $ultimo = null;
        for ($intento = 1; $intento <= 1 + $this->reintentos; $intento++) {
            if ($intento > 1) {
                ($this->esperar)(200 * 2 ** ($intento - 2));
            }
            try {
                $r = $this->transporte->enviar($metodo, $ruta, $cabeceras, $cuerpo);
            } catch (FallaTransporte) {
                continue;
            }
            $ultimo = $r;
            if ($r['estado'] < 500) {
                return new ResultadoCliente($r['estado'] < 400 ? ResultadoCliente::CONFIRMADO : ResultadoCliente::RECHAZADO, $r['estado'], $r['cuerpo'],
                    $clave, $requestId, $intento);
            }
        }

        // Un 503 VERIFICACION_NO_DISPONIBLE es una respuesta DEFINITIVA: el servidor no emitió (fail-closed). Con la
        // misma clave, una emisión anterior ya habría devuelto 200 YA_EMITIDA. No es incierto: es un rechazo.
        $tipo = ($ultimo['cuerpo']['error'] ?? null) === 'VERIFICACION_NO_DISPONIBLE' ? ResultadoCliente::RECHAZADO : ResultadoCliente::INCIERTO;

        return new ResultadoCliente($tipo, $ultimo['estado'] ?? null, $ultimo['cuerpo'] ?? [], $clave, $requestId, 1 + $this->reintentos);
    }
}

<?php

declare(strict_types=1);

namespace Qa\Cliente;

/**
 * Lo que el cliente sabe al final de una llamada:
 *  - CONFIRMADO: el servidor respondió 2xx;
 *  - RECHAZADO:  el servidor respondió 4xx (no se reintenta: hay que corregir los datos);
 *  - PENDIENTE:  un RESULTADO que no se pudo confirmar (sin respuesta, o 503). Queda en la bandeja del cliente con su
 *                Idempotency-Key y se reenvía después: reenviar con la misma clave nunca registra dos veces (R-12);
 *  - INCIERTO:   una operación SIN clave (planificar, cerrar…) que no tuvo respuesta. No se reintenta sola: el usuario
 *                consulta el estado antes de repetirla, porque repetirla podría duplicar o chocar con el estado.
 */
final readonly class ResultadoCliente
{
    public const CONFIRMADO = 'CONFIRMADO';
    public const RECHAZADO = 'RECHAZADO';
    public const PENDIENTE = 'PENDIENTE';
    public const INCIERTO = 'INCIERTO';

    /** @param array<string, mixed> $cuerpo */
    public function __construct(
        public string $tipo,
        public ?int $estado,
        public array $cuerpo,
        public ?string $clave,
        public string $requestId,
        public int $intentos,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Prescripciones\Cliente;

/**
 * Lo que el cliente sabe al final:
 *  - CONFIRMADO: el servidor respondió 2xx;
 *  - RECHAZADO:  el servidor respondió 4xx (no se reintenta: hay que corregir la prescripción);
 *  - INCIERTO:   no se sabe si quedó emitida. NUNCA se encola para enviar «después»: se informa al médico,
 *                y reintentar con la MISMA clave lo resuelve (200 YA_EMITIDA si se emitió).
 */
final readonly class ResultadoCliente
{
    public const CONFIRMADO = 'CONFIRMADO';
    public const RECHAZADO = 'RECHAZADO';
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

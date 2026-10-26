<?php

declare(strict_types=1);

namespace Pacientes\Cliente;

/** Lo que recibe el módulo consumidor: la respuesta y DE DÓNDE salió. */
final class ResultadoCliente
{
    public const SERVIDOR = 'SERVIDOR';
    public const CACHE_VALIDADA = 'CACHE_VALIDADA';     // 304: la copia local sigue vigente
    public const CACHE_DEGRADADA = 'CACHE_DEGRADADA';   // servidor caído: última copia conocida (puede estar vieja)
    public const FALLA_SERVICIO = 'FALLA_SERVICIO';
    public const CIRCUITO_ABIERTO = 'CIRCUITO_ABIERTO';
    public const RESULTADO_INCIERTO = 'RESULTADO_INCIERTO'; // PUT sin respuesta: puede haberse aplicado

    /** @param array<string, mixed> $cuerpo */
    public function __construct(
        public readonly ?int $estado,
        public readonly array $cuerpo,
        public readonly string $origen,
        public readonly string $requestId,
        public readonly int $intentos,
    ) {
    }

    public function disponible(): bool
    {
        return in_array($this->origen, [self::SERVIDOR, self::CACHE_VALIDADA, self::CACHE_DEGRADADA], true);
    }
}

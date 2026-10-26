<?php

declare(strict_types=1);

namespace Rbac\Cliente;

/** Respuesta del cliente a «¿puede este usuario hacer esto?». Nunca lanza: ante la duda, deniega. */
final class DecisionRemota
{
    public const ORIGEN_SERVICIO = 'SERVICIO';
    public const ORIGEN_FALLA = 'FALLA_SERVICIO';
    public const ORIGEN_CIRCUITO = 'CIRCUITO_ABIERTO';

    private function __construct(
        public readonly bool $permitido,
        public readonly string $origen,
        public readonly ?string $correlationId,
        public readonly int $intentos,
    ) {
    }

    public static function delServicio(bool $permitido, ?string $correlationId, int $intentos): self
    {
        return new self($permitido, self::ORIGEN_SERVICIO, $correlationId, $intentos);
    }

    /** Fail-closed: el servicio no respondió bien; se deniega sin haber decidido. */
    public static function denegadaPorFalla(string $origen, int $intentos): self
    {
        return new self(false, $origen, null, $intentos);
    }
}

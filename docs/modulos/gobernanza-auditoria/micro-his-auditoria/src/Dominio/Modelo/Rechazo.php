<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Modelo;

/**
 * Por qué una regla no deja registrar el movimiento. La categoría es de negocio
 * (prohibido, invalido); el código HTTP lo decide Presentación.
 */
final class Rechazo
{
    public const PROHIBIDO = 'prohibido';
    public const INVALIDO = 'invalido';

    /** @param array<string, string> $campos */
    public function __construct(
        public readonly string $categoria,
        public readonly string $codigo,
        public readonly array $campos = [],
    ) {
    }

    public static function prohibido(string $codigo): self
    {
        return new self(self::PROHIBIDO, $codigo);
    }

    /** @param array<string, string> $campos */
    public static function invalido(array $campos): self
    {
        return new self(self::INVALIDO, 'DATOS_INVALIDOS', $campos);
    }
}

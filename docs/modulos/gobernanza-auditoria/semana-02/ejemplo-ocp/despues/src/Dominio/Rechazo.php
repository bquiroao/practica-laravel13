<?php

declare(strict_types=1);

namespace Auditoria\Dominio;

/** Por qué una regla no deja registrar el movimiento. */
final class Rechazo
{
    /** @param array<string, string> $campos */
    public function __construct(
        public readonly string $estado,
        public readonly string $codigo,
        public readonly array $campos = [],
    ) {
    }

    public static function prohibido(string $codigo): self
    {
        return new self('prohibido', $codigo);
    }

    /** @param array<string, string> $campos */
    public static function invalido(array $campos): self
    {
        return new self('invalido', 'DATOS_INVALIDOS', $campos);
    }

    public function comoResultado(): Resultado
    {
        return Resultado::error($this->estado, $this->codigo, $this->campos === [] ? [] : ['campos' => $this->campos]);
    }
}

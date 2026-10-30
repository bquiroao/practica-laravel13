<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http\Formularios;

/** La entrada no tiene la FORMA esperada (400). Las reglas de negocio (422/409) las decide el dominio. */
final class EntradaInvalida extends \RuntimeException
{
    /** @param list<string> $campos */
    public function __construct(public readonly string $codigo, public readonly array $campos = [])
    {
        parent::__construct($codigo);
    }
}

<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Formularios;

/** La solicitud no tiene la FORMA esperada (400). Que los datos cumplan las reglas lo decide el dominio (422/409). */
final class EntradaInvalida extends \InvalidArgumentException
{
    /** @param list<string> $campos */
    public function __construct(public readonly string $codigo, public readonly array $campos = [])
    {
        parent::__construct($codigo);
    }
}

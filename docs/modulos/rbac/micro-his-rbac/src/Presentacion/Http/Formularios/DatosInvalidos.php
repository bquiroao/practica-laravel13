<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Formularios;

/** La entrada HTTP no tiene el formato esperado (campos faltantes o mal escritos). */
final class DatosInvalidos extends \InvalidArgumentException
{
    /** @param list<string> $campos */
    public function __construct(public readonly array $campos)
    {
        parent::__construct('Datos inválidos: ' . implode(', ', $campos));
    }
}

<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http\Formularios;

final readonly class EntradaVerificacion
{
    /** @param list<mixed> $items */
    public function __construct(public string $paciente, public array $items)
    {
    }
}

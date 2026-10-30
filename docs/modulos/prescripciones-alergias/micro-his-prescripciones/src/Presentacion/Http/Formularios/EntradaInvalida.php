<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http\Formularios;

/** La petición no tiene el formato esperado (400). No es una regla de negocio: esas son 422 del Dominio. */
final class EntradaInvalida extends \RuntimeException
{
    public function __construct(public readonly string $codigo)
    {
        parent::__construct($codigo);
    }
}

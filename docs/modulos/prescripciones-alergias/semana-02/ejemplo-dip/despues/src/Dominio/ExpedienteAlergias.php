<?php

declare(strict_types=1);

namespace Prescripciones\Dominio;

/**
 * Lo que la fuente de alergias sabe del paciente. «evaluado = false» significa que el expediente
 * no tiene ningún registro: NO significa «sin alergias» (R-06).
 */
final readonly class ExpedienteAlergias
{
    /** @param list<Alergia> $alergias */
    public function __construct(public bool $evaluado, public array $alergias)
    {
    }

    /** @return list<Alergia> */
    public function activas(): array
    {
        return array_values(array_filter($this->alergias, static fn (Alergia $a): bool => $a->activa()));
    }
}

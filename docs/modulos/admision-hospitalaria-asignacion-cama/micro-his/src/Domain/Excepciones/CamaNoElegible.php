<?php
declare(strict_types=1);

namespace Admision\Domain\Excepciones;

final class CamaNoElegible extends ErrorDeDominio
{
    /** @param list<string> $motivos */
    public function __construct(public readonly array $motivos)
    {
        parent::__construct('La cama no es compatible: ' . implode(' ', $motivos));
    }

    public function codigo(): string
    {
        return 'CAMA_NO_ELEGIBLE';
    }
}

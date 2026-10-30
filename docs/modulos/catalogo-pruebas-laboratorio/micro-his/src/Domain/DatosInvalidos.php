<?php
declare(strict_types=1);

namespace Lab\Domain;

/** RN-02, RN-03, RN-04: errores por campo (422). */
final class DatosInvalidos extends \DomainException
{
    /** @param array<string,string> $errores */
    public function __construct(public readonly array $errores)
    {
        parent::__construct('Datos inválidos: ' . implode('; ', $errores));
    }
}

<?php
declare(strict_types=1);

namespace Lab\Domain;

/** RN-06: la prueba no existe o no está vigente en la fecha (404). */
final class PruebaNoVigente extends \DomainException
{
    public function __construct(string $mensaje, public readonly ?Vigencia $vigencia = null)
    {
        parent::__construct($mensaje);
    }
}

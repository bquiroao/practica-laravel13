<?php
declare(strict_types=1);

namespace Admision\Domain\Excepciones;

/** Base de toda regla de dominio vulnerada. El código es estable y lo usa la API (semana 5). */
abstract class ErrorDeDominio extends \DomainException
{
    abstract public function codigo(): string;
}

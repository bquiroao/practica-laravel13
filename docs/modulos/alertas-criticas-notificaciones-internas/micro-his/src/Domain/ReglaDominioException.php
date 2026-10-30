<?php
declare(strict_types=1);

namespace AlertasCriticas\Domain;

/** Se lanza cuando una operación incumple una regla de negocio (RN-NN). */
final class ReglaDominioException extends \DomainException
{
    public function __construct(public readonly string $regla, string $mensaje)
    {
        parent::__construct("[$regla] $mensaje");
    }
}

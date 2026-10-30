<?php
declare(strict_types=1);

namespace Admision\Domain\Excepciones;

final class ConflictoConcurrencia extends ErrorDeDominio
{
    public function __construct(string $mensaje = 'Otra admisión está usando la cama en este momento. Intente de nuevo.')
    {
        parent::__construct($mensaje);
    }

    public function codigo(): string
    {
        return 'CONFLICTO_CONCURRENCIA';
    }
}

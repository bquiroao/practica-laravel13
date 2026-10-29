<?php
declare(strict_types=1);

namespace Admision\Domain\Excepciones;

final class CamaYaAsignada extends ErrorDeDominio
{
    public function __construct(string $mensaje = 'La cama ya fue asignada a otra admisión. Elija otra cama.')
    {
        parent::__construct($mensaje);
    }

    public function codigo(): string
    {
        return 'CAMA_YA_ASIGNADA';
    }
}

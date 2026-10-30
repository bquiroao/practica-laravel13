<?php
declare(strict_types=1);

namespace Admision\Domain\Excepciones;

final class AdmisionNoEncontrada extends ErrorDeDominio
{
    public function __construct(string $mensaje = 'La admisión no existe en este hospital.')
    {
        parent::__construct($mensaje);
    }

    public function codigo(): string
    {
        return 'ADMISION_NO_ENCONTRADA';
    }
}

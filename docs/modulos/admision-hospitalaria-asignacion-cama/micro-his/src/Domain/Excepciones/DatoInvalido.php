<?php
declare(strict_types=1);

namespace Admision\Domain\Excepciones;

final class DatoInvalido extends ErrorDeDominio
{
    public function __construct(string $mensaje = 'Dato inválido.')
    {
        parent::__construct($mensaje);
    }

    public function codigo(): string
    {
        return 'VALIDACION';
    }
}

<?php
declare(strict_types=1);

namespace Admision\Domain\Excepciones;

final class PacienteNoEncontrado extends ErrorDeDominio
{
    public function __construct(string $mensaje = 'El paciente no existe o no está activo en este hospital.')
    {
        parent::__construct($mensaje);
    }

    public function codigo(): string
    {
        return 'PACIENTE_NO_ENCONTRADO';
    }
}

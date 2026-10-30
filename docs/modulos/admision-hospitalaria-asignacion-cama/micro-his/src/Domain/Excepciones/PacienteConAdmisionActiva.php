<?php
declare(strict_types=1);

namespace Admision\Domain\Excepciones;

final class PacienteConAdmisionActiva extends ErrorDeDominio
{
    public function __construct(string $mensaje = 'El paciente ya tiene una admisión activa.')
    {
        parent::__construct($mensaje);
    }

    public function codigo(): string
    {
        return 'PACIENTE_CON_ADMISION_ACTIVA';
    }
}

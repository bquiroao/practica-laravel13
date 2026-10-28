<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Excepciones;

/** No existe en el hospital activo. Es el mismo error si existe en otro hospital (E-08). */
final class PacienteNoEncontrado extends ErrorDominio
{
    public function __construct()
    {
        parent::__construct(self::NO_ENCONTRADO, 'PACIENTE_NO_ENCONTRADO');
    }
}

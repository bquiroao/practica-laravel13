<?php
declare(strict_types=1);

namespace Alergias\Aplicacion;

use RuntimeException;

final class PacienteNoEncontrado extends RuntimeException
{
    public function __construct(public readonly string $codigoPaciente)
    {
        parent::__construct("No existe el paciente {$codigoPaciente} en este hospital.");
    }
}

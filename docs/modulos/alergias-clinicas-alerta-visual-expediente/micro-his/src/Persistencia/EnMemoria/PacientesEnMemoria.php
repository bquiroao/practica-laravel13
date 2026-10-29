<?php
declare(strict_types=1);

namespace Alergias\Persistencia\EnMemoria;

use Alergias\Dominio\DirectorioPacientes;
use Alergias\Dominio\PacienteRef;

final class PacientesEnMemoria implements DirectorioPacientes
{
    /** @param list<PacienteRef> $pacientes */
    public function __construct(private readonly array $pacientes)
    {
    }

    /** Los mismos datos ficticios que scripts/datos-demo.php. */
    public static function demo(): self
    {
        return new self([
            new PacienteRef('5a000000-0000-4000-8000-000000000451', 'PAC-00451', 'active'),
            new PacienteRef('5a000000-0000-4000-8000-000000000452', 'PAC-00452', 'active'),
            new PacienteRef('5a000000-0000-4000-8000-000000000453', 'PAC-00453', 'deceased'),
        ]);
    }

    public function porCodigo(string $codigo): ?PacienteRef
    {
        foreach ($this->pacientes as $paciente) {
            if ($paciente->codigo === $codigo) {
                return $paciente;
            }
        }
        return null;
    }
}

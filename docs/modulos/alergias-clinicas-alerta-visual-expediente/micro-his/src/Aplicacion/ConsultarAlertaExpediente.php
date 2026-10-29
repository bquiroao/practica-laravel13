<?php
declare(strict_types=1);

namespace Alergias\Aplicacion;

use Alergias\Dominio\AlertaExpediente;
use Alergias\Dominio\CalculadorAlerta;
use Alergias\Dominio\DirectorioPacientes;
use Alergias\Dominio\RepositorioAlergias;

/** CU-03 Consultar alerta del expediente (incluye CU-04 Calcular nivel). */
final class ConsultarAlertaExpediente
{
    public function __construct(
        private readonly DirectorioPacientes $pacientes,
        private readonly RepositorioAlergias $alergias,
        private readonly CalculadorAlerta $calculador,
    ) {
    }

    public function ejecutar(string $codigoPaciente): AlertaExpediente
    {
        $paciente = $this->pacientes->porCodigo($codigoPaciente)
            ?? throw new PacienteNoEncontrado($codigoPaciente);

        return $this->calculador->calcular($paciente->codigo, $this->alergias->activasDePaciente($paciente->id));
    }
}

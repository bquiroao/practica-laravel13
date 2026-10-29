<?php
declare(strict_types=1);

namespace Alergias\Aplicacion;

use Alergias\Dominio\AlertaExpediente;
use Alergias\Dominio\CalculadorAlerta;
use Alergias\Dominio\DirectorioPacientes;
use Alergias\Dominio\RepositorioAlergias;
use Alergias\Dominio\RepositorioReconocimientos;

/** CU-03 Consultar alerta del expediente (incluye CU-04 Calcular nivel). */
final class ConsultarAlertaExpediente
{
    public function __construct(
        private readonly DirectorioPacientes $pacientes,
        private readonly RepositorioAlergias $alergias,
        private readonly CalculadorAlerta $calculador,
        private readonly ?RepositorioReconocimientos $reconocimientos = null,
    ) {
    }

    /** Con $usuarioId indica si ESE usuario ya reconoció la huella vigente (semana 4). */
    public function ejecutar(string $codigoPaciente, ?string $usuarioId = null): AlertaExpediente
    {
        $paciente = $this->pacientes->porCodigo($codigoPaciente)
            ?? throw new PacienteNoEncontrado($codigoPaciente);
        $alerta = $this->calculador->calcular($paciente->codigo, $this->alergias->activasDePaciente($paciente->id));

        if ($usuarioId === null || $this->reconocimientos === null) {
            return $alerta;
        }
        return $alerta->conReconocimiento($this->reconocimientos->existe($paciente->id, $usuarioId, $alerta->huella));
    }
}

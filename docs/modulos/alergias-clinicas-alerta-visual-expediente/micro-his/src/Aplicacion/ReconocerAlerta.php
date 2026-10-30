<?php
declare(strict_types=1);

namespace Alergias\Aplicacion;

use Alergias\Dominio\AlertaExpediente;
use Alergias\Dominio\CalculadorAlerta;
use Alergias\Dominio\DirectorioPacientes;
use Alergias\Dominio\Reconocimiento;
use Alergias\Dominio\ReglaDominioViolada;
use Alergias\Dominio\RepositorioAlergias;
use Alergias\Dominio\RepositorioReconocimientos;

/** CU-05 Reconocer alerta: solo vale para la huella que el usuario vio (RN-09). */
final class ReconocerAlerta
{
    public function __construct(
        private readonly DirectorioPacientes $pacientes,
        private readonly RepositorioAlergias $alergias,
        private readonly RepositorioReconocimientos $reconocimientos,
        private readonly CalculadorAlerta $calculador,
    ) {
    }

    public function ejecutar(string $codigoPaciente, string $usuarioId, string $huellaVista): AlertaExpediente
    {
        $paciente = $this->pacientes->porCodigo($codigoPaciente)
            ?? throw new PacienteNoEncontrado($codigoPaciente);
        $alerta = $this->calculador->calcular($paciente->codigo, $this->alergias->activasDePaciente($paciente->id));

        if (!$alerta->nivel->exigeReconocimiento()) {
            throw new ReglaDominioViolada('RECONOCIMIENTO_NO_REQUERIDO', 'Esta alerta no requiere reconocimiento.');
        }
        if (!hash_equals($alerta->huella, $huellaVista)) {
            throw new ReglaDominioViolada('ALERTA_CAMBIO', 'Las alergias cambiaron desde que abrió la alerta. Revísela de nuevo.');
        }
        $this->reconocimientos->guardar(new Reconocimiento($paciente->id, $usuarioId, $alerta->nivel, $alerta->huella));

        return $alerta->conReconocimiento(true);
    }
}

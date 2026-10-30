<?php
declare(strict_types=1);

namespace Alergias\Aplicacion;

use Alergias\Dominio\Alergeno;
use Alergias\Dominio\Alergia;
use Alergias\Dominio\AlergiaDuplicada;
use Alergias\Dominio\AlertaExpediente;
use Alergias\Dominio\CalculadorAlerta;
use Alergias\Dominio\DirectorioPacientes;
use Alergias\Dominio\ReglaDominioViolada;
use Alergias\Dominio\Reloj;
use Alergias\Dominio\RepositorioAlergias;
use Alergias\Dominio\Severidad;
use Alergias\Dominio\TipoAlergia;
use DateTimeImmutable;

/** CU-01 Registrar alergia (incluye CU-02 Validar). Devuelve la alerta recalculada. */
final class RegistrarAlergia
{
    public function __construct(
        private readonly DirectorioPacientes $pacientes,
        private readonly RepositorioAlergias $alergias,
        private readonly CalculadorAlerta $calculador,
        private readonly Reloj $reloj,
    ) {
    }

    public function ejecutar(SolicitudRegistro $s): AlertaExpediente
    {
        $paciente = $this->pacientes->porCodigo($s->codigoPaciente)
            ?? throw new PacienteNoEncontrado($s->codigoPaciente);

        $alergia = Alergia::registrar(
            self::nuevoId(),
            $paciente,
            new Alergeno($s->alergenoCodigo, $s->alergenoNombre),
            TipoAlergia::desde($s->tipo),
            Severidad::desde($s->severidad),
            $s->reaccion,
            self::fecha($s->fechaInicio),
            $s->usuarioId,
            $this->reloj->hoy(),
        );

        if ($this->alergias->existeActivaEquivalente($paciente->id, $alergia->tipo, $alergia->alergeno->clave())) {
            throw new AlergiaDuplicada();
        }
        $this->alergias->guardar($alergia);

        return $this->calculador->calcular($paciente->codigo, $this->alergias->activasDePaciente($paciente->id));
    }

    private static function fecha(?string $texto): ?DateTimeImmutable
    {
        if ($texto === null || $texto === '') {
            return null;
        }
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
        if ($fecha === false || $fecha->format('Y-m-d') !== $texto) {
            throw new ReglaDominioViolada('FECHA_INVALIDA', 'La fecha de inicio debe tener el formato AAAA-MM-DD.');
        }
        return $fecha;
    }

    /** UUID v4 sin dependencias externas. */
    private static function nuevoId(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}

<?php
declare(strict_types=1);

namespace Alergias\Dominio;

use DateTimeImmutable;

/** Alergia activa de un paciente (tabla allergies). */
final class Alergia
{
    public const REACCION_MAXIMA = 500;

    private function __construct(
        public readonly string $id,
        public readonly string $pacienteId,
        public readonly Alergeno $alergeno,
        public readonly TipoAlergia $tipo,
        public readonly Severidad $severidad,
        public readonly ?string $reaccion,
        public readonly ?DateTimeImmutable $inicio,
        public readonly string $verificadaPor,
        public readonly string $estado = 'active',
    ) {
    }

    /** CU-02: valida RN-01, RN-02, RN-04 y RN-05 antes de crear. */
    public static function registrar(
        string $id,
        PacienteRef $paciente,
        Alergeno $alergeno,
        TipoAlergia $tipo,
        Severidad $severidad,
        ?string $reaccion,
        ?DateTimeImmutable $inicio,
        string $verificadaPor,
        DateTimeImmutable $hoy,
    ): self {
        if (!$paciente->estaActivo()) {
            throw new ReglaDominioViolada('PACIENTE_INACTIVO', 'Solo se registran alergias de pacientes activos.');
        }
        if ($reaccion !== null && trim($reaccion) === '') {
            throw new ReglaDominioViolada('REACCION_EN_BLANCO', 'La reacción no puede quedar en blanco.');
        }
        if ($reaccion !== null && mb_strlen($reaccion) > self::REACCION_MAXIMA) {
            throw new ReglaDominioViolada('REACCION_DEMASIADO_LARGA', 'La reacción admite hasta 500 caracteres.');
        }
        if ($inicio !== null && $inicio->format('Y-m-d') > $hoy->format('Y-m-d')) {
            throw new ReglaDominioViolada('FECHA_INICIO_FUTURA', 'La fecha de inicio no puede ser futura.');
        }
        return new self($id, $paciente->id, $alergeno, $tipo, $severidad, $reaccion, $inicio, $verificadaPor);
    }

    /** Reconstrucción desde persistencia (sin volver a validar). */
    public static function reconstruir(
        string $id,
        string $pacienteId,
        Alergeno $alergeno,
        TipoAlergia $tipo,
        Severidad $severidad,
        ?string $reaccion,
        ?DateTimeImmutable $inicio,
        string $verificadaPor,
        string $estado,
    ): self {
        return new self($id, $pacienteId, $alergeno, $tipo, $severidad, $reaccion, $inicio, $verificadaPor, $estado);
    }
}

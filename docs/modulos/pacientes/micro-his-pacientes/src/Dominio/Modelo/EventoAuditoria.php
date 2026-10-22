<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Modelo;

/**
 * Evento PACIENTE_* de la bitácora (R-09).
 * Semana 7: el formato de los eventos de escritura vive aquí y no en los casos de uso.
 */
final class EventoAuditoria
{
    public const REGISTRADO = 'PACIENTE_REGISTRADO';
    public const BUSCADO = 'PACIENTE_BUSCADO';
    public const CONSULTADO = 'PACIENTE_CONSULTADO';
    public const ACTUALIZADO = 'PACIENTE_ACTUALIZADO';

    /** @param array<string, mixed> $detalle */
    public function __construct(
        public readonly string $accion,
        public readonly string $actor,
        public readonly string $hospital,
        public readonly ?string $paciente,
        public readonly array $detalle,
    ) {
    }

    /**
     * El motivo justifica las coincidencias confirmadas (R-03) y, si es protegido, la protección (R-11).
     *
     * @param list<string> $confirmadas
     */
    public static function registrado(string $actor, Paciente $registrado, array $confirmadas, string $motivo): self
    {
        $motivo = trim($motivo);

        return new self(self::REGISTRADO, $actor, $registrado->hospital, $registrado->codigo, [
            'datos' => $registrado->instantanea(),
            'coincidencias_confirmadas' => $confirmadas,
            'motivo' => $confirmadas === [] ? null : $motivo,
            'motivo_proteccion' => $registrado->identidadProtegida ? $motivo : null,
        ]);
    }

    public static function actualizado(string $actor, Paciente $antes, Paciente $despues, string $motivo): self
    {
        $motivo = trim($motivo);

        return new self(self::ACTUALIZADO, $actor, $despues->hospital, $despues->codigo, [
            'antes' => $antes->instantanea(),
            'despues' => $despues->instantanea(),
            'motivo' => $motivo === '' ? null : $motivo,
        ]);
    }
}

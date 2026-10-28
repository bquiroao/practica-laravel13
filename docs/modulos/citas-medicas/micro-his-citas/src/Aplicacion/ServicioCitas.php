<?php
declare(strict_types=1);

namespace MicroHis\Citas\Aplicacion;

use MicroHis\Citas\Dominio\{EstadoCita, Horario, ReglaDeDominio};

/** Puertos: los define la aplicación y los implementan las capas externas (DIP de la semana 2). */
interface RepositorioCitas
{
    public function medicoVigente(string $medico, string $especialidad): bool;                     // R-04, R-05
    public function cruce(string $medico, string $paciente, int $inicio, int $fin): ?string;       // 'medico', 'paciente' o null
    public function guardar(string $medico, string $paciente, string $especialidad, Horario $h, string $motivo): int;
    public function estado(int $id): ?string;
    /** Cambia el estado solo si sigue siendo $de; guarda el motivo de cancelación (R-10). false = cambió antes. */
    public function cambiarEstado(int $id, string $de, string $a, ?string $motivo): bool;
}
interface Reloj { public function ahora(): int; }

/** Fallo técnico de la base: no es una regla de negocio. */
final class ErrorPersistencia extends \RuntimeException {}

/** Casos de uso CU-01 a CU-03 de la semana 1, con CU-04 (validar disponibilidad) incluido en solicitar. */
final class ServicioCitas
{
    public function __construct(private RepositorioCitas $repo, private Reloj $reloj) {}

    public function solicitar(string $medico, string $paciente, string $especialidad, int $inicio, int $fin, string $motivo): int
    {
        if (trim($motivo) === '') throw new ReglaDeDominio('E2', 'El motivo es obligatorio.');
        $horario = Horario::validar($inicio, $fin, $this->reloj->ahora());
        if (!$this->repo->medicoVigente($medico, $especialidad)) throw new ReglaDeDominio('E3', 'El médico no tiene esa especialidad vigente.');
        $cruce = $this->repo->cruce($medico, $paciente, $inicio, $fin);
        if ($cruce === 'medico') throw new ReglaDeDominio('E4', 'El médico ya tiene una cita en ese horario.');
        if ($cruce === 'paciente') throw new ReglaDeDominio('E5', 'El paciente ya tiene una cita en ese horario.');
        return $this->repo->guardar($medico, $paciente, $especialidad, $horario, $motivo);
    }

    public function confirmar(int $id): void { $this->cambiar($id, EstadoCita::Confirmada, null); }

    public function cancelar(int $id, string $motivo): void
    {
        trim($motivo) !== '' ? $this->cambiar($id, EstadoCita::Cancelada, $motivo) : throw new ReglaDeDominio('E2', 'El motivo de cancelación es obligatorio.');
    }

    private function cambiar(int $id, EstadoCita $nuevo, ?string $motivo): void
    {
        $actual = $this->repo->estado($id) ?? throw new ReglaDeDominio('NF', "La cita $id no existe.");
        EstadoCita::from($actual)->validarCambioA($nuevo);
        if (!$this->repo->cambiarEstado($id, $actual, $nuevo->value, $motivo)) throw new ReglaDeDominio('E7', 'La cita cambió mientras tanto: recarga.');
    }
}

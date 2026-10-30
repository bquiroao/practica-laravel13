<?php
declare(strict_types=1);

namespace MicroHis\Citas\Persistencia;

use MicroHis\Citas\Aplicacion\{HorarioTomado, RepositorioCitas};
use MicroHis\Citas\Dominio\Horario;

/** Adaptador en memoria del puerto RepositorioCitas: cumple el mismo contrato que RepositorioPdo, sin base. Para pruebas y demos. */
final class RepositorioEnMemoria implements RepositorioCitas
{
    /** @var array<int, array{medico: string, paciente: string, inicio: int, fin: int, estado: string}> */
    private array $citas = [];
    /** @var list<array{cita: int, motivo: string}> */
    private array $cancelaciones = [];

    /** @param list<array{0: string, 1: string}> $especialidadesVigentes pares [médico, especialidad] activos (doctor_specialties) */
    public function __construct(private array $especialidadesVigentes = []) {}

    public function medicoVigente(string $medico, string $especialidad): bool { return in_array([$medico, $especialidad], $this->especialidadesVigentes, true); }

    public function cruce(string $medico, string $paciente, int $inicio, int $fin): ?string
    {
        $cruzadas = array_filter($this->citas, fn (array $c) => in_array($c['estado'], ['scheduled', 'confirmed'], true) && $c['inicio'] < $fin && $c['fin'] > $inicio);
        if (array_filter($cruzadas, fn (array $c) => $c['medico'] === $medico)) return 'medico';   // el médico se informa primero, como en PDO
        return array_filter($cruzadas, fn (array $c) => $c['paciente'] === $paciente) ? 'paciente' : null;
    }

    public function guardar(string $medico, string $paciente, string $especialidad, Horario $h, string $motivo): int
    {
        if ($this->cruce($medico, $paciente, $h->inicio, $h->fin) !== null) throw new HorarioTomado('Otra solicitud tomó el horario.');   // misma barrera que PDO
        $this->citas[count($this->citas) + 1] = ['medico' => $medico, 'paciente' => $paciente, 'inicio' => $h->inicio, 'fin' => $h->fin, 'estado' => 'scheduled'];
        return count($this->citas);
    }

    public function estado(int $id): ?string { return $this->citas[$id]['estado'] ?? null; }

    public function cambiarEstado(int $id, string $de, string $a, ?string $motivo): bool
    {
        if (($this->citas[$id]['estado'] ?? null) !== $de) return false;   // concurrencia optimista, igual que el UPDATE ... WHERE status = ?
        $this->citas[$id]['estado'] = $a;
        if ($motivo !== null) $this->cancelaciones[] = ['cita' => $id, 'motivo' => $motivo];
        return true;
    }

    /** Motivos de cancelación (R-10); en PDO es la tabla appointment_cancellations. */
    public function cancelaciones(): array { return $this->cancelaciones; }
}

<?php

declare(strict_types=1);

// Dobles en memoria de los puertos. Solo para pruebas: producción usa los adaptadores PDO.

use Prescripciones\Aplicacion\Puertos\CatalogoMedicamentos;
use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Aplicacion\Puertos\Pacientes;
use Prescripciones\Aplicacion\Puertos\RegistroAuditoria;
use Prescripciones\Aplicacion\Puertos\Transaccion;
use Prescripciones\Dominio\Excepciones\ClaveEnUso;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;
use Prescripciones\Dominio\Modelo\Medicamento;
use Prescripciones\Dominio\Modelo\Prescripcion;
use Prescripciones\Dominio\Puertos\Reloj;
use Prescripciones\Dominio\Puertos\RepositorioPrescripciones;
use Prescripciones\Dominio\Reglas\VerificadorAlergias;

/** Participa en TransaccionEnMemoria: guarda y restaura su estado para simular el rollback. */
interface ParticipanteTransaccion
{
    public function instantanea(): mixed;

    public function restaurar(mixed $estado): void;
}

final class RepositorioEnMemoria implements RepositorioPrescripciones, ParticipanteTransaccion
{
    /** @var list<Prescripcion> */
    public array $guardadas = [];
    public bool $fallar = false;
    /** Simula una emisión concurrente que gana la carrera con la misma clave. */
    public ?Prescripcion $ganadoraConcurrente = null;
    /** @var list<Prescripcion> confirmadas por OTRA transacción: nuestro rollback no las toca */
    public array $deOtrasTransacciones = [];

    public function total(): int
    {
        return count($this->guardadas) + count($this->deOtrasTransacciones);
    }

    public function porClave(string $hospital, string $clave): ?Prescripcion
    {
        $this->revisar();
        foreach ([...$this->guardadas, ...$this->deOtrasTransacciones] as $p) {
            if ($p->hospital === $hospital && $p->clave === $clave) {
                return $p;
            }
        }

        return null;
    }

    public function porId(string $hospital, int $id): ?Prescripcion
    {
        $this->revisar();
        foreach ($this->guardadas as $p) {
            if ($p->hospital === $hospital && $p->id === $id) {
                return $p;
            }
        }

        return null;
    }

    public function guardar(Prescripcion $prescripcion): Prescripcion
    {
        $this->revisar();
        if ($this->ganadoraConcurrente !== null) {
            $this->deOtrasTransacciones[] = $this->ganadoraConcurrente->conId($this->total() + 1);
            $this->ganadoraConcurrente = null;
            throw new ClaveEnUso('carrera');
        }
        $guardada = $prescripcion->conId(count($this->guardadas) + 1);
        $this->guardadas[] = $guardada;

        return $guardada;
    }

    public function instantanea(): mixed
    {
        return $this->guardadas;
    }

    public function restaurar(mixed $estado): void
    {
        $this->guardadas = $estado;
    }

    private function revisar(): void
    {
        if ($this->fallar) {
            throw new ErrorPersistencia('base caída');
        }
    }
}

final class AuditoriaEnMemoria implements RegistroAuditoria, ParticipanteTransaccion
{
    /** @var list<array{accion: string, hospital: string, usuario: string, recurso: string, resultado: string}> */
    public array $movimientos = [];
    public bool $fallar = false;

    public function registrar(string $accion, string $hospital, string $usuario, string $recurso, string $resultado): void
    {
        if ($this->fallar) {
            throw new ErrorPersistencia('bandeja caída');
        }
        $this->movimientos[] = compact('accion', 'hospital', 'usuario', 'recurso', 'resultado');
    }

    /** @return list<string> */
    public function acciones(): array
    {
        return array_column($this->movimientos, 'accion');
    }

    public function instantanea(): mixed
    {
        return $this->movimientos;
    }

    public function restaurar(mixed $estado): void
    {
        $this->movimientos = $estado;
    }
}

final class TransaccionEnMemoria implements Transaccion
{
    public int $confirmadas = 0;
    public int $revertidas = 0;
    public bool $fallarAlConfirmar = false;

    /** @param list<ParticipanteTransaccion> $participantes */
    public function __construct(private readonly array $participantes)
    {
    }

    public function ejecutar(callable $trabajo): mixed
    {
        $estados = array_map(static fn (ParticipanteTransaccion $p): mixed => $p->instantanea(), $this->participantes);
        try {
            $resultado = $trabajo();
            if ($this->fallarAlConfirmar) {
                throw new ErrorPersistencia('COMMIT falló');
            }
            $this->confirmadas++;

            return $resultado;
        } catch (\Throwable $e) {
            foreach ($this->participantes as $i => $p) {
                $p->restaurar($estados[$i]);
            }
            $this->revertidas++;
            throw $e;
        }
    }
}

final class FuenteAlergiasEnMemoria implements FuenteAlergias
{
    public int $consultas = 0;

    /** @param array<string, list<Alergia>> $porPaciente */
    public function __construct(private array $porPaciente = [])
    {
    }

    public function agregar(string $paciente, Alergia $alergia): void
    {
        $this->porPaciente[$paciente][] = $alergia;
    }

    public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias
    {
        $this->consultas++;
        $alergias = $this->porPaciente[$paciente] ?? [];

        return new ExpedienteAlergias($alergias !== [], $alergias);
    }
}

final class FuenteAlergiasCaida implements FuenteAlergias
{
    public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias
    {
        throw new FuenteAlergiasNoDisponible('tiempo agotado');
    }
}

final class CatalogoEnMemoria implements CatalogoMedicamentos
{
    /** @var array<string, Medicamento> */
    private array $medicamentos = [];

    /** @param list<Medicamento> $medicamentos @param array<string, string> $grupos clave en minúsculas => grupo */
    public function __construct(array $medicamentos, private readonly array $grupos)
    {
        foreach ($medicamentos as $m) {
            $this->medicamentos[$m->codigo] = $m;
        }
    }

    public function buscar(string $codigo): ?Medicamento
    {
        return $this->medicamentos[$codigo] ?? null;
    }

    public function grupoDelAlergeno(Alergia $alergia): ?string
    {
        foreach ([$alergia->codigo !== null ? strtolower(trim($alergia->codigo)) : null,
            $alergia->nombre !== null ? VerificadorAlergias::normalizar($alergia->nombre) : null] as $clave) {
            if ($clave !== null && isset($this->grupos[$clave])) {
                return $this->grupos[$clave];
            }
        }

        return null;
    }
}

final class PacientesEnMemoria implements Pacientes
{
    public bool $fallar = false;

    /** @param array<string, list<string>> $porHospital */
    public function __construct(private readonly array $porHospital)
    {
    }

    public function existeEnHospital(string $paciente, string $hospital): bool
    {
        if ($this->fallar) {
            throw new ErrorPersistencia('base caída');
        }

        return in_array($paciente, $this->porHospital[$hospital] ?? [], true);
    }
}

final class RelojFijo implements Reloj
{
    public function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-27T15:00:00Z');
    }
}

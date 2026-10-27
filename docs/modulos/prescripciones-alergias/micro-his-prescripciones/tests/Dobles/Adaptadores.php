<?php

declare(strict_types=1);

/*
 * Dobles de prueba. Desde la semana 4 NO reimplementan los puertos: DECORAN los adaptadores InMemory de
 * producción (src/Persistencia/EnMemoria) y solo agregan la inyección de fallas que las pruebas necesitan.
 * Así las pruebas ejercitan el código real de producción.
 */

use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Aplicacion\Puertos\Pacientes;
use Prescripciones\Aplicacion\Puertos\RegistroAuditoria;
use Prescripciones\Aplicacion\Puertos\Transaccion;
use Prescripciones\Dominio\Excepciones\ClaveEnUso;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;
use Prescripciones\Dominio\Modelo\Prescripcion;
use Prescripciones\Dominio\Puertos\Reloj;
use Prescripciones\Dominio\Puertos\RepositorioPrescripciones;
use Prescripciones\Persistencia\EnMemoria\FuenteAlergiasEnMemoria;
use Prescripciones\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Prescripciones\Persistencia\EnMemoria\RepositorioPrescripcionesEnMemoria;
use Prescripciones\Persistencia\EnMemoria\TransaccionEnMemoria;

final class RepositorioConFallas implements RepositorioPrescripciones
{
    public bool $fallar = false;
    /** Simula una emisión concurrente que gana la carrera con la misma clave. */
    public ?Prescripcion $ganadoraConcurrente = null;
    /** @var list<Prescripcion> confirmadas por OTRA transacción: nuestro rollback no las toca */
    private array $deOtrasTransacciones = [];

    public function __construct(private readonly RepositorioPrescripcionesEnMemoria $interno)
    {
    }

    public function porClave(string $hospital, string $clave): ?Prescripcion
    {
        $this->revisar();
        foreach ($this->deOtrasTransacciones as $p) {
            if ($p->hospital === $hospital && $p->clave === $clave) {
                return $p;
            }
        }

        return $this->interno->porClave($hospital, $clave);
    }

    public function porId(string $hospital, int $id): ?Prescripcion
    {
        $this->revisar();

        return $this->interno->porId($hospital, $id);
    }

    public function guardar(Prescripcion $prescripcion): Prescripcion
    {
        $this->revisar();
        if ($this->ganadoraConcurrente !== null) {
            $this->deOtrasTransacciones[] = $this->ganadoraConcurrente->conId(count($this->todas()) + 1);
            $this->ganadoraConcurrente = null;
            throw new ClaveEnUso('carrera');
        }

        return $this->interno->guardar($prescripcion);
    }

    /** @return list<Prescripcion> */
    public function todas(): array
    {
        return [...$this->interno->todas(), ...$this->deOtrasTransacciones];
    }

    private function revisar(): void
    {
        if ($this->fallar) {
            throw new ErrorPersistencia('base caída');
        }
    }
}

final class AuditoriaConFallas implements RegistroAuditoria
{
    public bool $fallar = false;

    public function __construct(private readonly RegistroAuditoriaEnMemoria $interno)
    {
    }

    public function registrar(string $accion, string $hospital, string $usuario, string $recurso, string $resultado): void
    {
        if ($this->fallar) {
            throw new ErrorPersistencia('bandeja caída');
        }
        $this->interno->registrar($accion, $hospital, $usuario, $recurso, $resultado);
    }

    /** @return list<string> */
    public function acciones(): array
    {
        return $this->interno->acciones();
    }

    /** @return list<array<string, string>> */
    public function movimientos(): array
    {
        return $this->interno->movimientos();
    }
}

final class TransaccionConFallas implements Transaccion
{
    public int $confirmadas = 0;
    public int $revertidas = 0;
    public bool $fallarAlConfirmar = false;

    public function __construct(private readonly TransaccionEnMemoria $interna)
    {
    }

    public function ejecutar(callable $trabajo): mixed
    {
        try {
            $resultado = $this->interna->ejecutar(function () use ($trabajo): mixed {
                $r = $trabajo();
                if ($this->fallarAlConfirmar) {
                    throw new ErrorPersistencia('COMMIT falló');
                }

                return $r;
            });
            $this->confirmadas++;

            return $resultado;
        } catch (\Throwable $e) {
            $this->revertidas++;
            throw $e;
        }
    }
}

final class PacientesConFallas implements Pacientes
{
    public bool $fallar = false;

    public function __construct(private readonly Pacientes $interno)
    {
    }

    public function existeEnHospital(string $paciente, string $hospital): bool
    {
        if ($this->fallar) {
            throw new ErrorPersistencia('base caída');
        }

        return $this->interno->existeEnHospital($paciente, $hospital);
    }
}

final class FuenteAlergiasContada implements FuenteAlergias
{
    public int $consultas = 0;

    public function __construct(private readonly FuenteAlergiasEnMemoria $interna)
    {
    }

    public function agregar(string $paciente, Alergia $alergia): void
    {
        $this->interna->agregar($paciente, $alergia);
    }

    public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias
    {
        $this->consultas++;

        return $this->interna->alergiasDe($paciente, $hospital);
    }
}

final class FuenteAlergiasCaida implements FuenteAlergias
{
    public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias
    {
        throw new FuenteAlergiasNoDisponible('tiempo agotado');
    }
}

final class RelojFijo implements Reloj
{
    public function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-27T15:00:00Z');
    }
}

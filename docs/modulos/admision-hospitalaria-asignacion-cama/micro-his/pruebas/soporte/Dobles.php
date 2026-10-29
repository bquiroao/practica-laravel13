<?php
declare(strict_types=1);

// Dobles de prueba para los puertos del dominio. Permiten probar el caso de uso sin base de datos
// y provocar fallas (persistencia, conflicto) que con datos reales serían difíciles de reproducir.

namespace Pruebas\Dobles;

use Admision\Application\Puertos\GeneradorId;
use Admision\Application\Puertos\Reloj;
use Admision\Domain\Modelo\Admision;
use Admision\Domain\Modelo\CodigoPaciente;
use Admision\Domain\Modelo\TenantId;
use Admision\Domain\Repositorio\AdmisionRepository;
use Admision\Domain\Repositorio\CatalogoCamas;
use Admision\Domain\Repositorio\DirectorioPacientes;
use Pruebas\Datos;

final class DirectorioPacientesFalso implements DirectorioPacientes
{
    public function existeActivo(TenantId $tenantId, CodigoPaciente $paciente): bool
    {
        foreach (Datos::crudos()['pacientes'] as [$hospital, $codigo, $activo]) {
            if ($hospital === $tenantId->valor && $codigo === $paciente->valor) {
                return $activo;
            }
        }
        return false;
    }
}

final class CatalogoCamasFalso implements CatalogoCamas
{
    public function buscar(TenantId $tenantId, string $camaId): ?\Admision\Domain\Modelo\CamaDisponible
    {
        foreach ($this->listar($tenantId) as $cama) {
            if ($cama->id === $camaId) {
                return $cama;
            }
        }
        return null;
    }

    public function listar(TenantId $tenantId): array
    {
        return array_values(array_filter(Datos::camas(), fn ($c) => $c->tenantId->esIgualA($tenantId)));
    }
}

/** Espía: guarda lo que se registra y puede configurarse para fallar al registrar. */
final class AdmisionRepositoryEspia implements AdmisionRepository
{
    /** @var list<Admision> */
    public array $registradas = [];
    public ?\Throwable $fallaAlRegistrar = null;

    /** @param list<array{string,string}> $activasPrevias [hospital, código de paciente] */
    public function __construct(public array $activasPrevias = [], public array $camasOcupadasPrevias = [])
    {
    }

    public function registrar(Admision $admision): void
    {
        if ($this->fallaAlRegistrar !== null) {
            throw $this->fallaAlRegistrar;
        }
        $this->registradas[] = $admision;
    }

    public function tieneAdmisionActiva(TenantId $tenantId, CodigoPaciente $paciente): bool
    {
        if (in_array([$tenantId->valor, $paciente->valor], $this->activasPrevias, true)) {
            return true;
        }
        foreach ($this->registradas as $a) {
            if ($a->tenantId->esIgualA($tenantId) && $a->codigoPaciente->valor === $paciente->valor) {
                return true;
            }
        }
        return false;
    }

    public function camasOcupadas(TenantId $tenantId): array
    {
        $ids = $this->camasOcupadasPrevias;
        foreach ($this->registradas as $a) {
            if ($a->tenantId->esIgualA($tenantId)) {
                $ids[] = $a->camaId;
            }
        }
        return $ids;
    }

    public function buscarPorId(TenantId $tenantId, string $admisionId): ?Admision
    {
        foreach ($this->registradas as $a) {
            if ($a->id === $admisionId && $a->tenantId->esIgualA($tenantId)) {
                return $a;
            }
        }
        return null;
    }

    public function eventos(TenantId $tenantId, string $admisionId): array
    {
        $a = $this->buscarPorId($tenantId, $admisionId);
        return $a === null ? [] : $a->eventosPendientes();
    }
}

final class RelojFijo implements Reloj
{
    public function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-03-02T08:15:00+00:00');
    }
}

final class GeneradorIdSecuencial implements GeneradorId
{
    private int $n = 0;

    public function nuevo(): string
    {
        return sprintf('a0000000-0000-4000-8000-%012d', ++$this->n);
    }
}

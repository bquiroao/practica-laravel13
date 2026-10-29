<?php
declare(strict_types=1);

namespace Admision\Persistence\InMemory;

use Admision\Domain\Excepciones\CamaYaAsignada;
use Admision\Domain\Excepciones\PacienteConAdmisionActiva;
use Admision\Domain\Modelo\Admision;
use Admision\Domain\Modelo\CodigoPaciente;
use Admision\Domain\Modelo\EstadoAdmision;
use Admision\Domain\Modelo\TenantId;
use Admision\Domain\Repositorio\AdmisionRepository;

/**
 * Mismo contrato que PdoAdmisionRepository, en memoria (pruebas y demostraciones sin Docker).
 * Respeta RN-01, RN-02 y el «todo o nada»: valida antes de modificar el estado.
 * El estado vive lo que vive el proceso PHP.
 */
final class InMemoryAdmisionRepository implements AdmisionRepository
{
    /** @var array<string, Admision> */
    private array $admisiones = [];
    /** @var array<string, list<\Admision\Domain\Modelo\EventoAdmision>> */
    private array $eventos = [];

    public function registrar(Admision $admision): void
    {
        $tenant = $admision->tenantId;
        if (in_array($admision->camaId, $this->camasOcupadas($tenant), true)) {
            throw new CamaYaAsignada();
        }
        if ($this->tieneAdmisionActiva($tenant, $admision->codigoPaciente)) {
            throw new PacienteConAdmisionActiva();
        }
        $this->admisiones[$admision->id] = $admision;
        $this->eventos[$admision->id] = $admision->eventosPendientes();
    }

    public function tieneAdmisionActiva(TenantId $tenantId, CodigoPaciente $paciente): bool
    {
        foreach ($this->activas($tenantId) as $a) {
            if ($a->codigoPaciente->valor === $paciente->valor) {
                return true;
            }
        }
        return false;
    }

    public function camasOcupadas(TenantId $tenantId): array
    {
        return array_values(array_map(fn (Admision $a) => $a->camaId, $this->activas($tenantId)));
    }

    public function buscarPorId(TenantId $tenantId, string $admisionId): ?Admision
    {
        $a = $this->admisiones[$admisionId] ?? null;
        if ($a === null || !$a->tenantId->esIgualA($tenantId)) {
            return null;
        }
        // Se devuelve como la devolvería la base: sin eventos pendientes.
        return Admision::reconstituir($a->id, $a->tenantId, $a->codigoPaciente, $a->requerimiento, $a->origen,
            $a->estado, $a->camaId, $a->camaCodigo, $a->creadaPor, $a->creadaEn);
    }

    public function eventos(TenantId $tenantId, string $admisionId): array
    {
        return $this->buscarPorId($tenantId, $admisionId) === null ? [] : $this->eventos[$admisionId];
    }

    /** @return list<Admision> */
    private function activas(TenantId $tenantId): array
    {
        return array_values(array_filter(
            $this->admisiones,
            fn (Admision $a) => $a->tenantId->esIgualA($tenantId) && $a->estado === EstadoAdmision::ACTIVA,
        ));
    }
}

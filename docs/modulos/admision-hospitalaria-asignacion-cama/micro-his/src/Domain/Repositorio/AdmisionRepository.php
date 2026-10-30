<?php
declare(strict_types=1);

namespace Admision\Domain\Repositorio;

use Admision\Domain\Modelo\Admision;
use Admision\Domain\Modelo\CodigoPaciente;
use Admision\Domain\Modelo\EventoAdmision;
use Admision\Domain\Modelo\TenantId;

/**
 * Puerto de persistencia de los datos PROPIOS del módulo: admision, asignacion_cama, evento_admision.
 * Toda implementación debe cumplir el mismo contrato (prueba de contrato, semana 4).
 */
interface AdmisionRepository
{
    /**
     * Guarda admisión, asignación y eventos de forma atómica: todo o nada.
     * @throws \Admision\Domain\Excepciones\CamaYaAsignada          RN-01
     * @throws \Admision\Domain\Excepciones\PacienteConAdmisionActiva RN-02
     * @throws \Admision\Domain\Excepciones\ConflictoConcurrencia   otra transacción tiene la cama bloqueada
     * @throws \Admision\Domain\Excepciones\ErrorPersistencia       falla técnica; no quedan datos a medias
     */
    public function registrar(Admision $admision): void;

    public function tieneAdmisionActiva(TenantId $tenantId, CodigoPaciente $paciente): bool;

    /** @return list<string> ids de camas con asignación activa en el hospital */
    public function camasOcupadas(TenantId $tenantId): array;

    public function buscarPorId(TenantId $tenantId, string $admisionId): ?Admision;

    /** @return list<EventoAdmision> en orden de registro */
    public function eventos(TenantId $tenantId, string $admisionId): array;
}

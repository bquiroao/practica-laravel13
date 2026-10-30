<?php
declare(strict_types=1);

namespace Admision\Domain\Modelo;

use Admision\Domain\Elegibilidad\EvaluadorElegibilidad;
use Admision\Domain\Excepciones\CamaNoElegible;
use Admision\Domain\Excepciones\CamaNoEncontrada;

/** Entidad raíz: una admisión hospitalaria con su cama asignada. */
final class Admision
{
    /** @var list<EventoAdmision> eventos nuevos pendientes de guardar */
    private array $eventosPendientes = [];

    private function __construct(
        public readonly string $id,
        public readonly TenantId $tenantId,
        public readonly CodigoPaciente $codigoPaciente,
        public readonly Requerimiento $requerimiento,
        public readonly OrigenAdmision $origen,
        public readonly EstadoAdmision $estado,
        public readonly string $camaId,
        public readonly string $camaCodigo,
        public readonly Responsable $creadaPor,
        public readonly \DateTimeImmutable $creadaEn,
    ) {
    }

    /**
     * Abre la admisión y asigna la cama. Reglas que protege:
     * RN-03 compatibilidad, RN-04 cama operativa y del mismo hospital, RN-05 trazabilidad.
     * RN-01 (una cama, una asignación activa) la garantiza el repositorio dentro de su transacción.
     */
    public static function abrir(
        string $id,
        TenantId $tenantId,
        CodigoPaciente $paciente,
        Requerimiento $requerimiento,
        OrigenAdmision $origen,
        CamaDisponible $cama,
        EvaluadorElegibilidad $evaluador,
        Responsable $responsable,
        \DateTimeImmutable $ahora,
    ): self {
        if (!$cama->tenantId->esIgualA($tenantId)) {
            // No se revela que la cama existe en otro hospital.
            throw new CamaNoEncontrada();
        }
        $motivos = $evaluador->motivosDeRechazo($cama, $requerimiento);
        if ($motivos !== []) {
            throw new CamaNoElegible($motivos);
        }

        $admision = new self(
            $id, $tenantId, $paciente, $requerimiento, $origen,
            EstadoAdmision::ACTIVA, $cama->id, $cama->codigo, $responsable, $ahora,
        );
        $admision->eventosPendientes = [
            new EventoAdmision($id, $tenantId, TipoEvento::ADMISION_CREADA, $responsable, $ahora, [
                'codigo_paciente' => $paciente->valor,
                'origen' => $origen->value,
            ]),
            new EventoAdmision($id, $tenantId, TipoEvento::CAMA_ASIGNADA, $responsable, $ahora, [
                'cama_id' => $cama->id,
                'cama_codigo' => $cama->codigo,
            ]),
        ];
        return $admision;
    }

    /** Reconstruye una admisión ya guardada (sin eventos pendientes). */
    public static function reconstituir(
        string $id,
        TenantId $tenantId,
        CodigoPaciente $paciente,
        Requerimiento $requerimiento,
        OrigenAdmision $origen,
        EstadoAdmision $estado,
        string $camaId,
        string $camaCodigo,
        Responsable $creadaPor,
        \DateTimeImmutable $creadaEn,
    ): self {
        return new self($id, $tenantId, $paciente, $requerimiento, $origen, $estado, $camaId, $camaCodigo, $creadaPor, $creadaEn);
    }

    /** @return list<EventoAdmision> */
    public function eventosPendientes(): array
    {
        return $this->eventosPendientes;
    }
}

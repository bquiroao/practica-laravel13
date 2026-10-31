<?php

namespace App\Services\Admissions;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\AdmissionOrder;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\LocalPatient;
use App\Services\Admissions\Eligibility\BedRequirement;
use App\Services\Audit\AuditTrail;
use App\Services\Hospital\UserRefSync;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Mejora del módulo admisión: la orden de ingreso pendiente de cama. Se ordena el ingreso, el paciente espera y, al
 * asignarle una cama, AdmissionService crea el internamiento como siempre y cumple la orden. La espera es el tiempo
 * entre la orden y la asignación.
 */
class AdmissionOrderService
{
    public const PRIORITIES = ['urgente' => 'Urgente', 'programada' => 'Programada'];

    public const STATUSES = ['pendiente' => 'Pendiente de cama', 'asignada' => 'Cama asignada', 'cancelada' => 'Cancelada'];

    public function __construct(private readonly AdmissionService $admissions, private readonly UserRefSync $refs, private readonly AuditTrail $audit) {}

    /**
     * Ordenar el ingreso: el paciente activo, sin internamiento abierto y sin otra orden pendiente.
     *
     * @param  array{patient_id: string, bed_type: string, isolation: bool, source: string, priority: string, reason: string}  $data
     */
    public function order(CentralUser $actor, Hospital $hospital, array $data, ?string $ip): AdmissionOrder
    {
        $patient = LocalPatient::query()->find($data['patient_id']);
        if ($patient === null || $patient->status !== 'active') {
            throw new DomainRuleException('PACIENTE_NO_ENCONTRADO', 'El paciente no existe o no está activo en este hospital.', 404);
        }
        if (CareEpisode::query()->where('local_patient_id', $patient->local_patient_id)->where('type', 'inpatient')->where('status', 'open')->exists()) {
            throw new DomainRuleException('PACIENTE_CON_ADMISION_ACTIVA', 'El paciente ya está internado. Para cambiarlo de cama use «Trasladar».', 409);
        }
        if ($pending = $this->pendingFor($patient)) {
            throw $this->alreadyPending($pending);
        }
        new BedRequirement($data['bed_type'], (bool) $data['isolation']);

        try {
            $order = AdmissionOrder::query()->create([
                'local_patient_id' => $patient->local_patient_id, 'ordered_by_user_ref_id' => $this->refs->forUser($actor, $hospital)->local_user_ref_id,
                'bed_type' => $data['bed_type'], 'isolation' => (bool) $data['isolation'], 'source' => $data['source'], 'priority' => $data['priority'],
                'reason' => trim($data['reason']), 'status' => 'pendiente', 'ordered_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Dos órdenes a la vez para el mismo paciente: gana la primera (índice admission_orders_one_pending).
            if (str_contains($e->getMessage(), 'admission_orders_one_pending')) {
                throw $this->alreadyPending($this->pendingFor($patient));
            }
            throw $e;
        }

        $this->audit->record('admisiones', 'ORDEN_INGRESO_CREADA', 'exito', $hospital->hospital_id, $actor->user_id, 'orden-ingreso:'.$order->admission_order_id,
            ['paciente' => $patient->code, 'tipo_cama' => $order->bed_type, 'prioridad' => $order->priority], $ip);

        return $order;
    }

    /** Asignar la cama: es la misma admisión de siempre (CU-01), con los datos de la orden; la admisión cumple la orden. */
    public function assign(CentralUser $actor, Hospital $hospital, AdmissionOrder $order, string $bedId, ?string $wardId, ?string $ip, ?string $requestId): CareEpisode
    {
        $this->ensurePending($order);

        return $this->admissions->admit($actor, $hospital, [
            'patient_id' => $order->local_patient_id, 'bed_id' => $bedId, 'source' => $order->source, 'reason' => $order->reason,
            'requirement' => new BedRequirement($order->bed_type, $order->isolation, $wardId),
        ], $ip, $requestId);
    }

    /** Cancelar la orden con motivo, por ejemplo si el paciente mejoró o se refirió a otro centro. */
    public function cancel(CentralUser $actor, Hospital $hospital, AdmissionOrder $order, string $reason, ?string $ip): AdmissionOrder
    {
        $ref = $this->refs->forUser($actor, $hospital);
        DB::connection('hospital')->transaction(function () use ($order, $ref, $reason): void {
            $locked = AdmissionOrder::query()->lockForUpdate()->findOrFail($order->admission_order_id);
            $this->ensurePending($locked);
            $locked->forceFill(['status' => 'cancelada', 'cancelled_at' => now(), 'cancelled_by_user_ref_id' => $ref->local_user_ref_id, 'cancel_reason' => trim($reason)])->save();
        });
        $order->refresh();
        $this->audit->record('admisiones', 'ORDEN_INGRESO_CANCELADA', 'exito', $hospital->hospital_id, $actor->user_id, 'orden-ingreso:'.$order->admission_order_id,
            ['motivo' => $order->cancel_reason, 'espera_minutos' => (int) $order->ordered_at->diffInMinutes($order->cancelled_at)], $ip);

        return $order;
    }

    private function ensurePending(AdmissionOrder $order): void
    {
        if ($order->status !== 'pendiente') {
            throw new DomainRuleException('ORDEN_NO_PENDIENTE', $order->status === 'asignada'
                ? 'La orden de ingreso ya tiene cama asignada.' : 'La orden de ingreso fue cancelada.', 409);
        }
    }

    private function pendingFor(LocalPatient $patient): ?AdmissionOrder
    {
        return AdmissionOrder::query()->where('local_patient_id', $patient->local_patient_id)->where('status', 'pendiente')->first();
    }

    private function alreadyPending(?AdmissionOrder $order): DomainRuleException
    {
        return new DomainRuleException('ORDEN_PENDIENTE', 'El paciente ya tiene una orden de ingreso esperando cama.', 409,
            array_filter(['orden_id' => $order?->admission_order_id]));
    }
}

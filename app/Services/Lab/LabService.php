<?php

namespace App\Services\Lab;

use App\Events\LabResultCritical;
use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\LabOrder;
use App\Models\Hospital\LabOrderItem;
use App\Models\Hospital\LabResult;
use App\Models\Hospital\LocalPatient;
use App\Services\Audit\AuditTrail;
use App\Services\Catalogs\LabTestDirectory;
use App\Services\Hospital\UserRefSync;
use Illuminate\Support\Facades\DB;

/**
 * Flujo de laboratorio, un módulo por paso:
 *  - órdenes desde el EMR (ordenes-laboratorio-emr): al menos una prueba, sin repetir, con indicación clínica;
 *  - recepción de muestras (recepcion-muestras): código único por muestra, o rechazo con motivo;
 *  - ingreso de resultados (ingreso-resultados-laboratorio): en la unidad de la prueba, marcado según los rangos;
 *  - validación por bioquímico (validacion-resultados-bioquimico): otra persona valida o rechaza; lo validado no cambia.
 */
class LabService
{
    public function __construct(private readonly LabTestDirectory $tests, private readonly UserRefSync $refs, private readonly AuditTrail $audit) {}

    /** @param list<string> $codes */
    public function order(CentralUser $actor, Hospital $hospital, LocalPatient $patient, array $codes, string $indication, string $priority, ?string $ip): LabOrder
    {
        if ($patient->status !== 'active') {
            throw new DomainRuleException('PACIENTE_NO_ACTIVO', 'La ficha del paciente no está activa.');
        }
        $codes = array_map(fn ($c) => strtoupper(trim((string) $c)), $codes);
        if ($codes === []) {
            throw new DomainRuleException('PRUEBAS_INVALIDAS', 'Elija al menos una prueba.');
        }
        if (count(array_unique($codes)) !== count($codes)) {
            throw new DomainRuleException('PRUEBAS_INVALIDAS', 'Hay pruebas repetidas en la orden.');
        }
        $tests = array_map(fn ($c) => $this->tests->find($c) ?? throw new DomainRuleException('PRUEBAS_INVALIDAS', "La prueba {$c} no existe en el catálogo."), $codes);
        if (trim($indication) === '') {
            throw new DomainRuleException('INDICACION_REQUERIDA', 'Escriba la indicación clínica (para qué se pide).');
        }

        return DB::connection('hospital')->transaction(function () use ($actor, $hospital, $patient, $tests, $indication, $priority, $ip): LabOrder {
            $episode = CareEpisode::query()->where('local_patient_id', $patient->local_patient_id)->where('status', 'open')
                ->orderByRaw("type = 'inpatient' DESC")->value('care_episode_id');
            $order = LabOrder::query()->create([
                'local_patient_id' => $patient->local_patient_id, 'care_episode_id' => $episode, 'priority' => $priority,
                'ordered_by_user_ref_id' => $this->refs->forUser($actor, $hospital, 'medico')->local_user_ref_id, 'clinical_indication' => trim($indication), 'status' => 'ordered',
            ]);
            foreach ($tests as $t) {
                $order->items()->create(['test_code' => $t['code'], 'test_name' => $t['name'], 'specimen_type' => $t['specimen_type'], 'unit' => $t['unit'],
                    'ref_low' => $t['ref_low'], 'ref_high' => $t['ref_high'], 'critical_low' => $t['critical_low'], 'critical_high' => $t['critical_high']]);
            }
            $this->audit->record('laboratorio', 'ORDEN_CREADA', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$patient->code,
                ['orden' => $order->lab_order_id, 'pruebas' => array_column($tests, 'code')], $ip);

            return $order;
        });
    }

    public function receive(CentralUser $actor, Hospital $hospital, LabOrderItem $item, ?string $ip): LabOrderItem
    {
        $this->expect($item, ['pending'], 'Esta prueba ya no está pendiente de recibir.');

        return DB::connection('hospital')->transaction(function () use ($actor, $hospital, $item, $ip): LabOrderItem {
            $seq = (int) DB::connection('hospital')->scalar("SELECT nextval('lab_specimen_seq')");
            $code = 'M'.now($hospital->timezone)->format('ymd').'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            $item->forceFill(['status' => 'received', 'specimen_code' => $code, 'received_at' => now(),
                'received_by_user_ref_id' => $this->refs->forUser($actor, $hospital)->local_user_ref_id])->save();
            $this->refreshOrder($item->order);
            $this->audit->record('laboratorio', 'MUESTRA_RECIBIDA', 'exito', $hospital->hospital_id, $actor->user_id, 'muestra:'.$code, ['prueba' => $item->test_code], $ip);

            return $item;
        });
    }

    public function reject(CentralUser $actor, Hospital $hospital, LabOrderItem $item, string $reason, ?string $ip): LabOrderItem
    {
        $this->expect($item, ['pending'], 'Solo se rechaza una muestra que todavía no se recibió.');
        if (mb_strlen(trim($reason)) < 5) {
            throw new DomainRuleException('MOTIVO_REQUERIDO', 'Escriba por qué se rechaza la muestra (p. ej. «Muestra hemolizada»).');
        }
        $item->forceFill(['status' => 'rejected', 'rejection_reason' => trim($reason), 'received_by_user_ref_id' => $this->refs->forUser($actor, $hospital)->local_user_ref_id])->save();
        $this->refreshOrder($item->order);
        $this->audit->record('laboratorio', 'MUESTRA_RECHAZADA', 'exito', $hospital->hospital_id, $actor->user_id, 'item:'.$item->lab_order_item_id, ['motivo' => trim($reason)], $ip);

        return $item;
    }

    public function enterResult(CentralUser $actor, Hospital $hospital, LabOrderItem $item, float $value, string $unit, ?string $ip): LabResult
    {
        $this->expect($item, ['received', 'resulted'], 'La muestra todavía no se recibió o el resultado ya fue validado.');
        if (trim($unit) !== $item->unit) {
            throw new DomainRuleException('UNIDAD_DISTINTA', "El resultado debe estar en {$item->unit}.");
        }

        return DB::connection('hospital')->transaction(function () use ($actor, $hospital, $item, $value, $ip): LabResult {
            // Corregir antes de validar: el resultado anterior queda reemplazado, no se borra.
            $previous = LabResult::query()->where('lab_order_item_id', $item->lab_order_item_id)->where('status', 'captured')->lockForUpdate()->first();
            $previous?->forceFill(['status' => 'superseded'])->save();
            $result = LabResult::query()->create([
                'lab_order_item_id' => $item->lab_order_item_id, 'value_numeric' => $value, 'unit' => $item->unit, 'flag' => self::flag($item, $value),
                'status' => 'captured', 'entered_by_user_ref_id' => $this->refs->forUser($actor, $hospital)->local_user_ref_id,
                'version_no' => ($item->results()->max('version_no') ?? 0) + 1, 'supersedes_id' => $previous?->lab_result_id,
            ]);
            $item->forceFill(['status' => 'resulted'])->save();
            $this->audit->record('laboratorio', $previous ? 'RESULTADO_CORREGIDO' : 'RESULTADO_INGRESADO', 'exito', $hospital->hospital_id, $actor->user_id,
                'muestra:'.$item->specimen_code, ['valor' => $value, 'marca' => $result->flag], $ip);

            return $result;
        });
    }

    public function review(CentralUser $actor, Hospital $hospital, LabResult $result, bool $approve, string $note, ?string $ip): LabResult
    {
        if ($result->status !== 'captured') {
            throw new DomainRuleException('RESULTADO_YA_REVISADO', 'Este resultado ya fue validado o rechazado.', 409);
        }
        $ref = $this->refs->forUser($actor, $hospital, 'bioquimico');
        if ($ref->local_user_ref_id === $result->entered_by_user_ref_id) {
            throw new DomainRuleException('MISMA_PERSONA', 'Otro profesional debe validar el resultado que usted ingresó.', 403);
        }
        if (! $approve && mb_strlen(trim($note)) < 5) {
            throw new DomainRuleException('MOTIVO_REQUERIDO', 'Escriba por qué se rechaza el resultado.');
        }

        $reviewed = DB::connection('hospital')->transaction(function () use ($actor, $hospital, $result, $approve, $note, $ref, $ip): LabResult {
            $item = $result->item()->lockForUpdate()->firstOrFail();
            if ($approve) {
                $result->forceFill(['status' => 'validated', 'validated_at' => now(), 'validated_by_user_ref_id' => $ref->local_user_ref_id, 'review_note' => trim($note) ?: null])->save();
                $item->forceFill(['status' => 'validated'])->save();
            } else {
                // Rechazado: el laboratorio vuelve a procesar e ingresa otro valor.
                $result->forceFill(['status' => 'rejected', 'review_note' => trim($note)])->save();
                $item->forceFill(['status' => 'received'])->save();
            }
            $this->refreshOrder($item->order);
            $this->audit->record('laboratorio', $approve ? 'RESULTADO_VALIDADO' : 'RESULTADO_RECHAZADO', 'exito', $hospital->hospital_id, $actor->user_id,
                'muestra:'.$item->specimen_code, ['marca' => $result->flag] + ($approve ? [] : ['motivo' => trim($note)]), $ip);

            return $result;
        });

        if ($approve && str_starts_with($reviewed->flag, 'critical')) {
            $item = $reviewed->item()->with('order.patient')->first();
            LabResultCritical::dispatch($hospital->hospital_id, $item->order->local_patient_id, $item->order->patient->code, $item->test_code, $item->test_name,
                $reviewed->value_numeric, $item->unit, $reviewed->flag);
        }

        return $reviewed;
    }

    public static function flag(LabOrderItem $item, float $value): string
    {
        if ($item->critical_low !== null && $value < $item->critical_low) {
            return 'critical_low';
        }
        if ($item->critical_high !== null && $value > $item->critical_high) {
            return 'critical_high';
        }
        if ($item->ref_low !== null && $value < $item->ref_low) {
            return 'low';
        }

        return $item->ref_high !== null && $value > $item->ref_high ? 'high' : 'normal';
    }

    private function expect(LabOrderItem $item, array $states, string $message): void
    {
        if ($item->order->status === 'cancelled' || ! in_array($item->status, $states, true)) {
            throw new DomainRuleException('ESTADO_INVALIDO', $message, 409);
        }
    }

    /** La orden avanza sola: en proceso al recibir la primera muestra, completa cuando no queda nada por validar. */
    private function refreshOrder(LabOrder $order): void
    {
        $states = $order->items()->pluck('status');
        $status = $states->every(fn ($s) => in_array($s, ['validated', 'rejected'], true)) ? 'completed'
            : ($states->contains(fn ($s) => $s !== 'pending') ? 'in_process' : 'ordered');
        $order->forceFill(['status' => $status])->save();
    }
}

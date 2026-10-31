<?php

namespace App\Services\Alerts\Listeners;

use App\Events\LabResultCritical;
use App\Models\Central\Hospital;
use App\Services\Alerts\CriticalAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Un resultado crítico validado crea una alerta CRITICA. El id de origen es el del resultado validado, así el mismo
 * resultado no crea dos alertas (RN-02). Si algo falla aquí, el laboratorio no se entera: la validación ya se guardó.
 */
class CreateAlertFromLabResult
{
    private const FLAGS = ['critical_low' => 'crítico bajo', 'critical_high' => 'crítico alto'];

    public function __construct(private readonly CriticalAlertService $alerts) {}

    public function handle(LabResultCritical $event): void
    {
        try {
            $hospital = Hospital::query()->findOrFail($event->hospitalId);
            $result = DB::connection('hospital')->table('lab_results as r')
                ->join('lab_order_items as i', 'i.lab_order_item_id', '=', 'r.lab_order_item_id')->join('lab_orders as o', 'o.lab_order_id', '=', 'i.lab_order_id')
                ->where('o.local_patient_id', $event->patientId)->where('i.test_code', $event->testCode)->where('r.status', 'validated')->where('r.flag', $event->flag)
                ->orderByDesc('r.validated_at')->first(['r.lab_result_id', 'o.care_episode_id']);
            $value = rtrim(rtrim(number_format($event->value, 4, '.', ''), '0'), '.');

            $this->alerts->register($hospital, [
                'patient_id' => $event->patientId, 'patient_code' => $event->patientCode, 'care_episode_id' => $result?->care_episode_id,
                'source' => 'laboratorio', 'source_id' => $result->lab_result_id ?? "{$event->patientId}:{$event->testCode}:{$event->flag}:{$value}",
                'priority' => 'CRITICA', 'title' => "Resultado crítico: {$event->testName}",
                'description' => "{$event->testName} ({$event->testCode}): {$value} {$event->unit}, ".(self::FLAGS[$event->flag] ?? $event->flag).'.',
            ]);
        } catch (\Throwable $e) {
            Log::error('alertas.laboratorio', ['paciente' => $event->patientCode, 'prueba' => $event->testCode, 'error' => $e->getMessage()]);
        }
    }
}

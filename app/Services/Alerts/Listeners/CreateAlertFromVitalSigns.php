<?php

namespace App\Services\Alerts\Listeners;

use App\Events\VitalSignsOutOfRange;
use App\Models\Central\Hospital;
use App\Services\Alerts\CriticalAlertService;
use Illuminate\Support\Facades\Log;

/**
 * Una toma de signos vitales fuera de rango crea una alerta: CRITICA si algún valor es crítico, ALTA si solo hay
 * valores anormales. El id de origen es la toma (RN-02). Si algo falla aquí, la toma ya quedó guardada.
 */
class CreateAlertFromVitalSigns
{
    public function __construct(private readonly CriticalAlertService $alerts) {}

    public function handle(VitalSignsOutOfRange $event): void
    {
        try {
            $critical = $event->isCritical();
            $findings = collect($event->findings)->sortByDesc(fn ($f) => $f['nivel'] === 'critica')
                ->map(fn ($f) => "{$f['etiqueta']} {$f['valor']}".($f['nivel'] === 'critica' ? ' (crítico)' : ''))->implode(', ');

            $this->alerts->register(Hospital::query()->findOrFail($event->hospitalId), [
                'patient_id' => $event->patientId, 'patient_code' => $event->patientCode, 'care_episode_id' => $event->careEpisodeId,
                'source' => 'signos_vitales', 'source_id' => $event->vitalSignId, 'priority' => $critical ? 'CRITICA' : 'ALTA',
                'title' => $critical ? 'Signos vitales críticos' : 'Signos vitales fuera de rango', 'description' => "Toma con valores fuera de rango: {$findings}.",
            ]);
        } catch (\Throwable $e) {
            Log::error('alertas.signos_vitales', ['paciente' => $event->patientCode, 'toma' => $event->vitalSignId, 'error' => $e->getMessage()]);
        }
    }
}

<?php

namespace App\Services\Analytics\Indicators;

use App\Models\Hospital\CriticalAlert;
use App\Services\Alerts\CriticalAlertService;
use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;

/**
 * Alertas críticas reconocidas a tiempo: alertas del período acusadas antes de su primer escalamiento ÷ alertas del
 * período × 100. «A tiempo» es acusarla en el nivel 0, dentro del plazo de su prioridad.
 */
class CriticalAlertIndicator extends Indicator
{
    public function key(): string
    {
        return 'alertas_a_tiempo';
    }

    public function name(): string
    {
        return 'Alertas críticas reconocidas a tiempo';
    }

    public function area(): string
    {
        return 'Clínico';
    }

    public function unit(): string
    {
        return '%';
    }

    public function description(): string
    {
        return 'De cada 100 alertas críticas, cuántas acusó el personal dentro del plazo, sin que hubiera que escalarlas.';
    }

    public function formula(): string
    {
        return 'alertas acusadas en el nivel 0 / alertas creadas en el período × 100';
    }

    public function source(): string
    {
        return 'critical_alerts';
    }

    public function dimensions(): array
    {
        return ['prioridad' => 'Prioridad', 'origen' => 'Origen'];
    }

    public function columns(): array
    {
        return ['fecha' => 'Creada', 'paciente' => 'Paciente', 'origen' => 'Origen', 'prioridad' => 'Prioridad', 'minutos' => 'Minutos hasta el acuse'];
    }

    public function numeratorLabel(): string
    {
        return 'alertas acusadas a tiempo';
    }

    public function denominatorLabel(): string
    {
        return 'alertas creadas';
    }

    public function note(): string
    {
        return 'Una alerta que todavía espera acuse cuenta como no reconocida a tiempo. Plazos: crítica 15 min y alta 30 min, configurables.';
    }

    public function feeds(): array
    {
        return ['alertas-criticas-notificaciones-internas'];
    }

    public function items(Filters $f): array
    {
        return $this->query($f)->where('acknowledged_level', 0)->get()->map(fn (CriticalAlert $a) => [
            'peso' => 1, 'dims' => ['prioridad' => CriticalAlertService::PRIORITIES[$a->priority], 'origen' => CriticalAlertService::SOURCES[$a->source]],
            'fila' => ['fecha' => $f->localDateTime($a->created_at), 'paciente' => $a->patient_code, 'origen' => CriticalAlertService::SOURCES[$a->source],
                'prioridad' => CriticalAlertService::PRIORITIES[$a->priority], 'minutos' => (int) $a->created_at->diffInMinutes($a->acknowledged_at)],
        ])->all();
    }

    public function denominator(Filters $f, array $items): int
    {
        return $this->query($f)->count();
    }

    private function query(Filters $f)
    {
        return CriticalAlert::query()->when($f->started(), fn ($q) => $q->whereBetween('created_at', $f->range()), fn ($q) => $q->whereRaw('false'))
            ->orderBy('created_at')->orderBy('critical_alert_id');
    }
}

<?php

namespace App\Services\Analytics\Indicators;

use App\Models\Hospital\Allergy;
use App\Models\Hospital\LocalPatient;
use App\Services\Allergies\AlertCalculator;
use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;

/**
 * Alergias de alto riesgo: alergias registradas en el período que encienden una alerta ALTA o CRÍTICA ÷ alergias
 * registradas × 100. El nivel lo decide el mismo calculador del módulo de alergias (severidad y anafilaxia), no una copia.
 */
class HighRiskAllergyIndicator extends Indicator
{
    public function __construct(private readonly AlertCalculator $calculator) {}

    public function key(): string
    {
        return 'alergias_alto_riesgo';
    }

    public function name(): string
    {
        return 'Alergias de alto riesgo';
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
        return 'De cada 100 alergias registradas, cuántas exigen que el personal reconozca la alerta en el expediente.';
    }

    public function formula(): string
    {
        return 'alergias con alerta ALTA o CRÍTICA / alergias registradas en el período × 100';
    }

    public function source(): string
    {
        return 'allergies, local_patients';
    }

    public function dimensions(): array
    {
        return ['nivel' => 'Nivel de alerta', 'alergeno' => 'Alérgeno'];
    }

    public function columns(): array
    {
        return ['fecha' => 'Registrada', 'paciente' => 'Paciente', 'nivel' => 'Nivel', 'estado' => 'Estado'];
    }

    public function numeratorLabel(): string
    {
        return 'alergias de alto riesgo';
    }

    public function denominatorLabel(): string
    {
        return 'alergias registradas';
    }

    public function note(): string
    {
        return 'Severidad grave o desconocida = ALTA; que pone en riesgo la vida o con reacción anafiláctica = CRÍTICA.';
    }

    public function feeds(): array
    {
        return ['alergias-clinicas-alerta-visual-expediente'];
    }

    public function items(Filters $f): array
    {
        $codes = [];
        $items = [];
        foreach ($this->query($f)->get() as $allergy) {
            $level = $this->calculator->levelFor($allergy);
            if (! $level->requiresAcknowledgement()) {
                continue;
            }
            $codes[$allergy->local_patient_id] ??= LocalPatient::query()->whereKey($allergy->local_patient_id)->value('code');
            $items[] = ['peso' => 1, 'dims' => ['nivel' => $level->label(), 'alergeno' => mb_convert_case(AlertCalculator::allergen($allergy), MB_CASE_TITLE)],
                'fila' => ['fecha' => $f->localDateTime($allergy->recorded_at), 'paciente' => $codes[$allergy->local_patient_id], 'nivel' => $level->label(),
                    'estado' => ['active' => 'Activa', 'inactive' => 'Inactiva', 'resolved' => 'Resuelta'][$allergy->status] ?? $allergy->status]];
        }

        return $items;
    }

    public function denominator(Filters $f, array $items): int
    {
        return $this->query($f)->count();
    }

    private function query(Filters $f)
    {
        return Allergy::query()->when($f->started(), fn ($q) => $q->whereBetween('recorded_at', $f->range()), fn ($q) => $q->whereRaw('false'))
            ->orderBy('recorded_at')->orderBy('allergy_id');
    }
}

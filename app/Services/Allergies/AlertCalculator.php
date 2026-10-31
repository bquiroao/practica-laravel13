<?php

namespace App\Services\Allergies;

use App\Models\Hospital\Allergy;
use App\Services\Allergies\Rules\AlertRule;
use App\Services\Allergies\Rules\AnaphylaxisRule;
use App\Services\Allergies\Rules\SeverityRule;
use Illuminate\Support\Collection;

/** Módulo alergias clínicas (CU-04): nivel = el mayor que asigne cualquier regla a cualquier alergia activa (RN-08). */
class AlertCalculator
{
    /** @var list<AlertRule> */
    private array $rules;

    public function __construct()
    {
        $this->rules = [new SeverityRule, new AnaphylaxisRule];
    }

    public function levelFor(Allergy $allergy): AlertLevel
    {
        $level = AlertLevel::Informative;
        foreach ($this->rules as $rule) {
            $proposed = $rule->levelFor($allergy);
            if ($proposed !== null) {
                $level = AlertLevel::max($level, $proposed);
            }
        }

        return $level;
    }

    /**
     * @param  Collection<int, Allergy>  $active
     * @return array{level: AlertLevel, detail: list<array{allergy: Allergy, level: AlertLevel}>, fingerprint: string}
     */
    public function calculate(Collection $active): array
    {
        $detail = $active->map(fn (Allergy $a) => ['allergy' => $a, 'level' => $this->levelFor($a)])
            ->sort(fn ($x, $y) => [$y['level']->weight(), self::allergen($x['allergy'])] <=> [$x['level']->weight(), self::allergen($y['allergy'])])
            ->values()->all();
        $level = array_reduce($detail, fn (AlertLevel $carry, array $d) => AlertLevel::max($carry, $d['level']), AlertLevel::NoRecord);

        return ['level' => $level, 'detail' => $detail, 'fingerprint' => self::fingerprint($active)];
    }

    /** RN-09: cambia si cambia el conjunto de alergias activas o su severidad. */
    public static function fingerprint(Collection $active): string
    {
        $parts = $active->map(fn (Allergy $a) => $a->allergy_id.':'.$a->severity)->sort()->values()->all();

        return substr(hash('sha256', implode('|', $parts)), 0, 16);
    }

    public static function allergen(Allergy $allergy): string
    {
        return trim((string) ($allergy->allergen_name ?: $allergy->allergen_code));
    }
}

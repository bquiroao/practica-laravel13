<?php

namespace App\Services\Allergies\Rules;

use App\Models\Hospital\Allergy;
use App\Services\Allergies\AlertLevel;

/** RN-08: el nivel según la severidad. La severidad desconocida se trata como grave (DEC-03). */
final class SeverityRule implements AlertRule
{
    public function levelFor(Allergy $allergy): ?AlertLevel
    {
        return match ($allergy->severity) {
            'life_threatening' => AlertLevel::Critical,
            'severe', 'unknown' => AlertLevel::High,
            'moderate' => AlertLevel::Moderate,
            default => AlertLevel::Informative,
        };
    }
}

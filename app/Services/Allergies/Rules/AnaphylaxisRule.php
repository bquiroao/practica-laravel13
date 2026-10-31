<?php

namespace App\Services\Allergies\Rules;

use App\Models\Hospital\Allergy;
use App\Services\Allergies\AlertLevel;
use Illuminate\Support\Str;

/**
 * Una reacción anafiláctica siempre es CRÍTICA, aunque la severidad se haya anotado menor.
 * Corrige el defecto D-02 de la semana 15: el texto se compara sin tildes ni mayúsculas, así «Reacción anafiláctica»,
 * «ANAFILAXIA» o «shock anafiláctico» se detectan igual.
 */
final class AnaphylaxisRule implements AlertRule
{
    public function levelFor(Allergy $allergy): ?AlertLevel
    {
        $reaction = Str::lower(Str::ascii((string) $allergy->reaction));

        return str_contains($reaction, 'anafila') ? AlertLevel::Critical : null;
    }
}

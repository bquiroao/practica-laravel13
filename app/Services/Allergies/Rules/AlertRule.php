<?php

namespace App\Services\Allergies\Rules;

use App\Models\Hospital\Allergy;
use App\Services\Allergies\AlertLevel;

/** Punto de extensión OCP (semana 2 del módulo): una regla nueva es una clase nueva; el calculador no cambia. */
interface AlertRule
{
    /** El nivel que esta regla asigna a la alergia, o null si no aplica. */
    public function levelFor(Allergy $allergy): ?AlertLevel;
}

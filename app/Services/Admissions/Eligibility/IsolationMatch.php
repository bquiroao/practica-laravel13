<?php

namespace App\Services\Admissions\Eligibility;

use App\Models\Hospital\Bed;

/** RN-03: quien requiere aislamiento va a una cama de aislamiento, y esas camas se reservan para quien lo requiere. */
final class IsolationMatch implements BedCriterion
{
    public function rejection(Bed $bed, BedRequirement $requirement): ?string
    {
        $isolationBed = $bed->type === 'aislamiento';
        if ($isolationBed === $requirement->isolation) {
            return null;
        }

        return $requirement->isolation ? 'El paciente requiere una cama de aislamiento.' : 'La cama de aislamiento se reserva para quien la requiere.';
    }
}

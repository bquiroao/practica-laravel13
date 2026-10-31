<?php

namespace App\Services\Admissions\Eligibility;

use App\Models\Hospital\Bed;

/** RN-03: el tipo de cama debe coincidir con el que necesita el paciente. */
final class SameBedType implements BedCriterion
{
    public function rejection(Bed $bed, BedRequirement $requirement): ?string
    {
        if ($requirement->isolation) {
            return null; // Lo decide IsolationMatch: con aislamiento solo vale una cama de aislamiento.
        }

        return $bed->type === $requirement->bedType ? null : 'El tipo de cama no coincide: es '.mb_strtolower(Bed::TYPES[$bed->type] ?? $bed->type).'.';
    }
}

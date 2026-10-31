<?php

namespace App\Services\Admissions\Eligibility;

use App\Models\Hospital\Bed;

/** RN-03: si se pidió una sala (el servicio), la cama debe ser de esa sala. */
final class SameWard implements BedCriterion
{
    public function rejection(Bed $bed, BedRequirement $requirement): ?string
    {
        return $requirement->wardId === null || $bed->ward_id === $requirement->wardId ? null : 'La cama es de otra sala.';
    }
}

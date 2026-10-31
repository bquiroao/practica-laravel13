<?php

namespace App\Services\Admissions\Eligibility;

use App\Models\Hospital\Bed;

/** OCP (semana 2 del módulo): cada criterio nuevo es una clase nueva; el evaluador no cambia. */
interface BedCriterion
{
    /** null si la cama cumple; si no, el motivo en lenguaje claro. */
    public function rejection(Bed $bed, BedRequirement $requirement): ?string;
}

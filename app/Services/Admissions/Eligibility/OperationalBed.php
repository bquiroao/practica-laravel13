<?php

namespace App\Services\Admissions\Eligibility;

use App\Models\Hospital\Bed;

/** RN-04: solo camas disponibles de una sala activa. «Ocupada» se mira en la estancia abierta, no solo en el estado. */
final class OperationalBed implements BedCriterion
{
    public function rejection(Bed $bed, BedRequirement $requirement): ?string
    {
        if ($bed->ward?->status !== 'active') {
            return 'La sala de la cama no está activa.';
        }
        if ($bed->openStay !== null) {
            return 'La cama ya está ocupada.';
        }

        return $bed->status === 'available' ? null : 'La cama no está disponible (en mantenimiento o inactiva).';
    }
}

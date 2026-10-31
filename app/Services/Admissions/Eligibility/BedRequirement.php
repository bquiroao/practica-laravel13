<?php

namespace App\Services\Admissions\Eligibility;

use App\Exceptions\DomainRuleException;
use App\Models\Hospital\Bed;

/** Módulo admisión: lo que necesita el paciente (RN-03): tipo de cama, aislamiento y, si se pide, una sala. */
final class BedRequirement
{
    public function __construct(
        public readonly string $bedType,
        public readonly bool $isolation,
        public readonly ?string $wardId = null,
    ) {
        if (! array_key_exists($bedType, Bed::TYPES)) {
            throw new DomainRuleException('VALIDACION', 'Elija un tipo de cama: general, UCI, aislamiento o pediátrica.');
        }
    }
}

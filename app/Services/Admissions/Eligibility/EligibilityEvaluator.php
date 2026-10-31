<?php

namespace App\Services\Admissions\Eligibility;

use App\Models\Hospital\Bed;

/** Cerrado a modificación: solo conoce la interfaz BedCriterion. Un criterio nuevo se agrega en la lista. */
final class EligibilityEvaluator
{
    /** @var list<BedCriterion> */
    private array $criteria;

    public function __construct()
    {
        $this->criteria = [new OperationalBed, new SameWard, new SameBedType, new IsolationMatch];
    }

    /** @return list<string> lista vacía = la cama es elegible */
    public function rejections(Bed $bed, BedRequirement $requirement): array
    {
        return array_values(array_filter(array_map(fn (BedCriterion $c) => $c->rejection($bed, $requirement), $this->criteria)));
    }
}

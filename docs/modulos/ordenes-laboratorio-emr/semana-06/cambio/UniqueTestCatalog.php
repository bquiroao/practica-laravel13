<?php

declare(strict_types=1);

require_once __DIR__ . '/../../semana-02/ejemplo-srp/src/LabOrderWorkflow.php';

/** Cambio de diseño del experimento: cada código aparece una vez por orden. */
final class UniqueTestCatalog implements LabCatalog
{
    public function __construct(private LabCatalog $catalog) {}

    public function validateTests(array $testCodes): ValidationDecision
    {
        if (count(array_unique($testCodes, SORT_STRING)) !== count($testCodes)) {
            return ValidationDecision::INVALID;
        }
        return $this->catalog->validateTests($testCodes);
    }
}
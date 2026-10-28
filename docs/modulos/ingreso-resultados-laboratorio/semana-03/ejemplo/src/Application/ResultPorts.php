<?php

declare(strict_types=1);

namespace LabResults\Application;

use LabResults\Domain\LabResultRecord;
use LabResults\Domain\PendingLabResult;

interface LabResultRepository
{
    public function currentFor(string $orderItemId): ?LabResultRecord;

    public function saveInitial(PendingLabResult $result): bool;

    public function appendCorrection(string $expectedCurrentVersionId, PendingLabResult $corrected): bool;
}

interface ResultIdentifierGenerator
{
    public function generate(): string;
}

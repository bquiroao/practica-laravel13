<?php

declare(strict_types=1);

namespace LabResults\Application;

use LabResults\Domain\LabResultRecord;

enum OperationStatus: string
{
    case CAPTURED = 'captured';
    case CORRECTED = 'corrected';
    case REJECTED = 'rejected';
    case CONFLICT = 'conflict';
    case NOT_FOUND = 'not_found';
}

final readonly class ResultOperation
{
    public function __construct(
        public OperationStatus $status,
        public ?LabResultRecord $result,
        public string $message,
    ) {
    }
}

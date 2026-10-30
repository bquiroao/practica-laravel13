<?php

declare(strict_types=1);

enum LabResultStatus: string
{
    case PENDING = 'PENDING';
    case VALIDATED = 'VALIDATED';
    case REJECTED = 'REJECTED';
}

enum ReviewDecision: string
{
    case VALIDATE = 'VALIDATE';
    case REJECT = 'REJECT';
}

enum DecisionWriteStatus: string
{
    case SAVED = 'SAVED';
    case CONFLICT = 'CONFLICT';
    case NOT_FOUND = 'NOT_FOUND';
    case FAILED = 'FAILED';
}

enum ReviewOutcomeStatus: string
{
    case VALIDATED = 'VALIDATED';
    case REJECTED = 'REJECTED';
    case DENIED = 'DENIED';
    case INVALID_INPUT = 'INVALID_INPUT';
    case INVALID_CONTEXT = 'INVALID_CONTEXT';
    case INVALID_STATE = 'INVALID_STATE';
    case NOT_FOUND = 'NOT_FOUND';
    case CONFLICT = 'CONFLICT';
    case PERSISTENCE_FAILURE = 'PERSISTENCE_FAILURE';
}

final readonly class LabResult
{
    public function __construct(
        public string $resultId,
        public string $hospitalId,
        public string $orderItemId,
        public string $examinationId,
        public string $specimenId,
        public int $version,
        public string $value,
        public string $unit,
        public LabResultStatus $status,
    ) {
    }
}

final readonly class ReviewCommand
{
    public function __construct(
        public string $resultId,
        public int $expectedVersion,
        public string $actorId,
        public string $hospitalId,
        public ReviewDecision $decision,
        public string $reason = '',
    ) {
    }
}

final readonly class ReviewDecisionRecord
{
    public function __construct(
        public string $resultId,
        public int $expectedVersion,
        public string $hospitalId,
        public string $actorId,
        public ReviewDecision $decision,
        public string $reason,
        public DateTimeImmutable $decidedAt,
    ) {
    }
}

final readonly class ReviewOutcome
{
    public function __construct(
        public ReviewOutcomeStatus $status,
        public ?ReviewDecisionRecord $record = null,
    ) {
    }
}

interface ReviewerAuthorization
{
    public function canReview(string $actorId, string $hospitalId): bool;
}

interface Clock
{
    public function now(): DateTimeImmutable;
}

interface LabResultDecisionRepository
{
    public function findById(string $resultId, string $hospitalId): ?LabResult;

    public function decideAtomically(ReviewDecisionRecord $record): DecisionWriteStatus;
}

final class PersistenceFailure extends RuntimeException {}
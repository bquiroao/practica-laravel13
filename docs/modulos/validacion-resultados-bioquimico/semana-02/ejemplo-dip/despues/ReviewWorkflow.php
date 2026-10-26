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
    public function findById(string $resultId): ?LabResult;

    public function decideAtomically(ReviewDecisionRecord $record): DecisionWriteStatus;
}

final class ReviewPendingLabResult
{
    public function __construct(
        private ReviewerAuthorization $authorization,
        private Clock $clock,
        private LabResultDecisionRepository $repository,
    ) {
    }

    public function execute(ReviewCommand $command): ReviewOutcome
    {
        if (
            trim($command->resultId) === ''
            || trim($command->actorId) === ''
            || trim($command->hospitalId) === ''
            || $command->expectedVersion < 1
        ) {
            return new ReviewOutcome(ReviewOutcomeStatus::INVALID_INPUT);
        }

        if (!$this->authorization->canReview($command->actorId, $command->hospitalId)) {
            return new ReviewOutcome(ReviewOutcomeStatus::DENIED);
        }

        $result = $this->repository->findById($command->resultId);
        if ($result === null || $result->hospitalId !== $command->hospitalId) {
            return new ReviewOutcome(ReviewOutcomeStatus::NOT_FOUND);
        }

        if (
            trim($result->orderItemId) === ''
            || trim($result->examinationId) === ''
            || trim($result->specimenId) === ''
        ) {
            return new ReviewOutcome(ReviewOutcomeStatus::INVALID_CONTEXT);
        }

        if ($result->version !== $command->expectedVersion) {
            return new ReviewOutcome(ReviewOutcomeStatus::CONFLICT);
        }

        if ($result->status !== LabResultStatus::PENDING) {
            return new ReviewOutcome(ReviewOutcomeStatus::INVALID_STATE);
        }

        $reason = trim($command->reason);
        if ($command->decision === ReviewDecision::REJECT && $reason === '') {
            return new ReviewOutcome(ReviewOutcomeStatus::INVALID_INPUT);
        }

        $record = new ReviewDecisionRecord(
            resultId: $command->resultId,
            expectedVersion: $command->expectedVersion,
            hospitalId: $command->hospitalId,
            actorId: $command->actorId,
            decision: $command->decision,
            reason: $reason,
            decidedAt: $this->clock->now(),
        );

        return match ($this->repository->decideAtomically($record)) {
            DecisionWriteStatus::SAVED => new ReviewOutcome(
                $command->decision === ReviewDecision::VALIDATE
                    ? ReviewOutcomeStatus::VALIDATED
                    : ReviewOutcomeStatus::REJECTED,
                $record,
            ),
            DecisionWriteStatus::CONFLICT => new ReviewOutcome(ReviewOutcomeStatus::CONFLICT),
            DecisionWriteStatus::NOT_FOUND => new ReviewOutcome(ReviewOutcomeStatus::NOT_FOUND),
            DecisionWriteStatus::FAILED => new ReviewOutcome(ReviewOutcomeStatus::PERSISTENCE_FAILURE),
        };
    }
}

<?php
declare(strict_types=1);
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

        try { $result = $this->repository->findById($command->resultId, $command->hospitalId); } catch (PersistenceFailure) { return new ReviewOutcome(ReviewOutcomeStatus::PERSISTENCE_FAILURE); }
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

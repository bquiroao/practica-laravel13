<?php

declare(strict_types=1);

require_once __DIR__ . '/../../semana-02/ejemplo-dip/despues/ReviewWorkflow.php';
require_once __DIR__ . '/../../semana-02/ejemplo-dip/despues/InMemoryLabResultDecisionRepository.php';

final class TestReviewerAuthorization implements ReviewerAuthorization
{
    public int $calls = 0;
    public ?string $lastActorId = null;
    public ?string $lastHospitalId = null;

    public function __construct(private bool $allowed = true)
    {
    }

    public function canReview(string $actorId, string $hospitalId): bool
    {
        $this->calls++;
        $this->lastActorId = $actorId;
        $this->lastHospitalId = $hospitalId;

        return $this->allowed;
    }
}

final class TestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-25T12:15:00-06:00');
    }
}

final class ObservedDecisionRepository implements LabResultDecisionRepository
{
    public int $findCalls = 0;
    public int $writeCalls = 0;

    public function __construct(
        public InMemoryLabResultDecisionRepository $inner,
        private ?DecisionWriteStatus $forcedWriteStatus = null,
    ) {
    }

    public function findById(string $resultId): ?LabResult
    {
        $this->findCalls++;

        return $this->inner->findById($resultId);
    }

    public function decideAtomically(ReviewDecisionRecord $record): DecisionWriteStatus
    {
        $this->writeCalls++;

        return $this->forcedWriteStatus ?? $this->inner->decideAtomically($record);
    }
}

final class RacingDecisionRepository implements LabResultDecisionRepository
{
    public function __construct(private InMemoryLabResultDecisionRepository $inner)
    {
    }

    public function findById(string $resultId): ?LabResult
    {
        $snapshot = $this->inner->findById($resultId);
        if ($snapshot !== null) {
            $this->inner->seed(new LabResult(
                resultId: $snapshot->resultId,
                hospitalId: $snapshot->hospitalId,
                orderItemId: $snapshot->orderItemId,
                examinationId: $snapshot->examinationId,
                specimenId: $snapshot->specimenId,
                version: $snapshot->version + 1,
                value: $snapshot->value,
                unit: $snapshot->unit,
                status: LabResultStatus::PENDING,
            ));
        }

        return $snapshot;
    }

    public function decideAtomically(ReviewDecisionRecord $record): DecisionWriteStatus
    {
        return $this->inner->decideAtomically($record);
    }
}

function specimenResult(
    string $resultId = 'RESULT-15',
    string $hospitalId = 'HOSPITAL-A',
    int $version = 1,
    LabResultStatus $status = LabResultStatus::PENDING,
    string $orderItemId = 'ORDER-ITEM-15',
    string $examinationId = 'EXAM-15',
    string $specimenId = 'SPECIMEN-15',
    string $value = '7.25',
    string $unit = 'mmol/L',
): LabResult {
    return new LabResult(
        resultId: $resultId,
        hospitalId: $hospitalId,
        orderItemId: $orderItemId,
        examinationId: $examinationId,
        specimenId: $specimenId,
        version: $version,
        value: $value,
        unit: $unit,
        status: $status,
    );
}

function reviewCommand(
    ReviewDecision $decision = ReviewDecision::VALIDATE,
    string $resultId = 'RESULT-15',
    int $version = 1,
    string $actorId = 'BIOCHEMIST-15',
    string $hospitalId = 'HOSPITAL-A',
    string $reason = '',
): ReviewCommand {
    return new ReviewCommand($resultId, $version, $actorId, $hospitalId, $decision, $reason);
}

/**
 * @return array{ReviewPendingLabResult, TestReviewerAuthorization, ObservedDecisionRepository}
 */
function reviewFixture(
    ?LabResult $result = null,
    bool $authorized = true,
    ?DecisionWriteStatus $writeStatus = null,
    bool $seed = true,
): array {
    $inner = new InMemoryLabResultDecisionRepository();
    if ($seed) {
        $inner->seed($result ?? specimenResult());
    }
    $repository = new ObservedDecisionRepository($inner, $writeStatus);
    $authorization = new TestReviewerAuthorization($authorized);
    $workflow = new ReviewPendingLabResult($authorization, new TestClock(), $repository);

    return [$workflow, $authorization, $repository];
}

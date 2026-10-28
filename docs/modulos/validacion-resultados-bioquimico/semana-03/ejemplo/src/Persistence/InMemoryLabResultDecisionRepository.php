<?php

declare(strict_types=1);

final class InMemoryLabResultDecisionRepository implements LabResultDecisionRepository
{
    /** @var array<string, LabResult> */
    private array $results = [];

    /** @var list<ReviewDecisionRecord> */
    private array $decisions = [];

    private bool $failNextWrite = false;

    public function seed(LabResult $result): void
    {
        $this->results[$result->hospitalId][$result->resultId] = $result;
    }

    public function findById(string $resultId, string $hospitalId): ?LabResult
    {
        return $this->results[$hospitalId][$resultId] ?? null;
    }

    public function decideAtomically(ReviewDecisionRecord $record): DecisionWriteStatus
    {
        $current = $this->results[$record->hospitalId][$record->resultId] ?? null;
        if ($current === null || $current->hospitalId !== $record->hospitalId) {
            return DecisionWriteStatus::NOT_FOUND;
        }

        if (
            $current->version !== $record->expectedVersion
            || $current->status !== LabResultStatus::PENDING
        ) {
            return DecisionWriteStatus::CONFLICT;
        }

        if ($this->failNextWrite) {
            $this->failNextWrite = false;
            return DecisionWriteStatus::FAILED;
        }

        $this->results[$record->hospitalId][$record->resultId] = new LabResult(
            resultId: $current->resultId,
            hospitalId: $current->hospitalId,
            orderItemId: $current->orderItemId,
            examinationId: $current->examinationId,
            specimenId: $current->specimenId,
            version: $current->version,
            value: $current->value,
            unit: $current->unit,
            status: $record->decision === ReviewDecision::VALIDATE
                ? LabResultStatus::VALIDATED
                : LabResultStatus::REJECTED,
        );
        $this->decisions[] = $record;

        return DecisionWriteStatus::SAVED;
    }

    public function failNextWrite(): void
    {
        $this->failNextWrite = true;
    }

    /** @return list<ReviewDecisionRecord> */
    public function decisions(): array
    {
        return $this->decisions;
    }
}

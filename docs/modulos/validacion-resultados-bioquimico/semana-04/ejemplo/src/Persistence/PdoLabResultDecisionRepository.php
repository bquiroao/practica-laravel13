<?php

declare(strict_types=1);

final class PdoLabResultDecisionRepository implements LabResultDecisionRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function findById(string $resultId): ?LabResult
    {
        try {
            $statement = $this->connection->prepare(
                'SELECT id, hospital_id, order_item_id, examination_id, specimen_id,
                        version, value, unit, status
                 FROM lab_result
                 WHERE id = :id',
            );
            $statement->execute(['id' => $resultId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            throw new ReviewRepositoryUnavailable(
                'No se pudo consultar el resultado.',
                previous: $exception,
            );
        }

        if ($row === false) {
            return null;
        }

        return new LabResult(
            resultId: (string) $row['id'],
            hospitalId: (string) $row['hospital_id'],
            orderItemId: (string) $row['order_item_id'],
            examinationId: (string) $row['examination_id'],
            specimenId: (string) $row['specimen_id'],
            version: (int) $row['version'],
            value: (string) $row['value'],
            unit: (string) $row['unit'],
            status: LabResultStatus::from((string) $row['status']),
        );
    }

    public function decideAtomically(ReviewDecisionRecord $record): DecisionWriteStatus
    {
        try {
            $this->connection->beginTransaction();

            $currentStatement = $this->connection->prepare(
                'SELECT version, status
                 FROM lab_result
                 WHERE id = :id AND hospital_id = :hospital',
            );
            $currentStatement->execute([
                'id' => $record->resultId,
                'hospital' => $record->hospitalId,
            ]);
            $current = $currentStatement->fetch(PDO::FETCH_ASSOC);

            if ($current === false) {
                $this->connection->rollBack();

                return DecisionWriteStatus::NOT_FOUND;
            }

            if (
                (int) $current['version'] !== $record->expectedVersion
                || $current['status'] !== LabResultStatus::PENDING->value
            ) {
                $this->connection->rollBack();

                return DecisionWriteStatus::CONFLICT;
            }

            $update = $this->connection->prepare(
                'UPDATE lab_result
                 SET status = :status
                 WHERE id = :id
                   AND hospital_id = :hospital
                   AND version = :version
                   AND status = :pending',
            );
            $update->execute([
                'status' => $record->decision === ReviewDecision::VALIDATE
                    ? LabResultStatus::VALIDATED->value
                    : LabResultStatus::REJECTED->value,
                'id' => $record->resultId,
                'hospital' => $record->hospitalId,
                'version' => $record->expectedVersion,
                'pending' => LabResultStatus::PENDING->value,
            ]);

            if ($update->rowCount() !== 1) {
                $this->connection->rollBack();

                return DecisionWriteStatus::CONFLICT;
            }

            $audit = $this->connection->prepare(
                'INSERT INTO lab_result_decision
                    (result_id, version, hospital_id, actor_id, decision, reason, decided_at)
                 VALUES
                    (:result, :version, :hospital, :actor, :decision, :reason, :decided_at)',
            );
            $audit->execute([
                'result' => $record->resultId,
                'version' => $record->expectedVersion,
                'hospital' => $record->hospitalId,
                'actor' => $record->actorId,
                'decision' => $record->decision->value,
                'reason' => $record->reason,
                'decided_at' => $record->decidedAt->format(DATE_ATOM),
            ]);

            $this->connection->commit();

            return DecisionWriteStatus::SAVED;
        } catch (PDOException) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            return DecisionWriteStatus::FAILED;
        }
    }
}

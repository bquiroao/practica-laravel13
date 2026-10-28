<?php

declare(strict_types=1);

final class CoupledLabResultReview
{
    public function __construct(private PDO $connection)
    {
    }

    public function decide(
        string $resultId,
        int $expectedVersion,
        string $actorId,
        string $hospitalId,
        string $role,
        string $decision,
        string $reason = '',
    ): string {
        if ($role !== 'bioquimico') {
            return 'DENIED';
        }
        if (!in_array($decision, ['VALIDATE', 'REJECT'], true)) {
            return 'INVALID_INPUT';
        }
        if ($decision === 'REJECT' && trim($reason) === '') {
            return 'INVALID_INPUT';
        }

        $this->connection->beginTransaction();

        try {
            $query = $this->connection->prepare(
                'SELECT version, status FROM lab_result WHERE id = :id AND hospital_id = :hospital'
            );
            $query->execute(['id' => $resultId, 'hospital' => $hospitalId]);
            $result = $query->fetch(PDO::FETCH_ASSOC);

            if ($result === false || $result['status'] !== 'PENDING') {
                $this->connection->rollBack();
                return 'INVALID_STATE';
            }
            if ((int) $result['version'] !== $expectedVersion) {
                $this->connection->rollBack();
                return 'CONFLICT';
            }

            $update = $this->connection->prepare(
                'UPDATE lab_result SET status = :status '
                . 'WHERE id = :id AND hospital_id = :hospital AND version = :version AND status = :pending'
            );
            $update->execute([
                'status' => $decision === 'VALIDATE' ? 'VALIDATED' : 'REJECTED',
                'id' => $resultId,
                'hospital' => $hospitalId,
                'version' => $expectedVersion,
                'pending' => 'PENDING',
            ]);
            if ($update->rowCount() !== 1) {
                $this->connection->rollBack();
                return 'CONFLICT';
            }

            $audit = $this->connection->prepare(
                'INSERT INTO lab_result_decision (result_id, actor_id, decision, reason, decided_at) '
                . 'VALUES (:result, :actor, :decision, :reason, :decided_at)'
            );
            $audit->execute([
                'result' => $resultId,
                'actor' => $actorId,
                'decision' => $decision,
                'reason' => trim($reason),
                'decided_at' => date(DATE_ATOM),
            ]);

            $this->connection->commit();
            return $decision === 'VALIDATE' ? 'VALIDATED' : 'REJECTED';
        } catch (PDOException) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            return 'PERSISTENCE_FAILURE';
        }
    }
}

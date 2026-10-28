<?php

declare(strict_types=1);

namespace LabResults\Persistence;

use DateTimeImmutable;
use LabResults\Application\LabResultRepository;
use LabResults\Domain\LabResultRecord;
use LabResults\Domain\PendingLabResult;
use LabResults\Domain\ResultState;
use LabResults\Domain\ResultVersion;
use LabResults\Domain\ValidatedLabResult;
use PDO;

final class PdoLabResultRepository implements LabResultRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function currentFor(string $orderItemId): ?LabResultRecord
    {
        $statement = $this->connection->prepare(
            'SELECT version_id, order_item_id, result_value, state, version_number,
                    previous_version_id, correction_reason, entered_by, entered_at
             FROM lab_result_versions
             WHERE order_item_id = :order_item_id
             ORDER BY version_number DESC
             LIMIT 1',
        );
        $statement->execute(['order_item_id' => $orderItemId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function saveInitial(PendingLabResult $result): bool
    {
        $version = $result->version();
        $statement = $this->connection->prepare(
            'INSERT INTO lab_result_versions
                (version_id, order_item_id, result_value, state, version_number,
                 previous_version_id, correction_reason, entered_by, entered_at)
             SELECT :version_id, :order_item_id, :result_value, :state, :version_number,
                    NULL, NULL, :entered_by, :entered_at
             WHERE NOT EXISTS (
                SELECT 1 FROM lab_result_versions WHERE order_item_id = :existing_item_id
             )',
        );
        $statement->execute([
            'version_id' => $version->versionId,
            'order_item_id' => $version->orderItemId,
            'result_value' => $version->value,
            'state' => $version->state->value,
            'version_number' => $version->versionNumber,
            'entered_by' => $version->enteredBy,
            'entered_at' => $version->enteredAt->format(DATE_ATOM),
            'existing_item_id' => $version->orderItemId,
        ]);

        return $statement->rowCount() === 1;
    }

    public function appendCorrection(string $expectedCurrentVersionId, PendingLabResult $corrected): bool
    {
        $version = $corrected->version();
        $parameters = $this->parameters($version);
        $parameters['expected_version_id'] = $expectedCurrentVersionId;
        $parameters['current_item_id'] = $version->orderItemId;
        $statement = $this->connection->prepare(
            'INSERT INTO lab_result_versions
                (version_id, order_item_id, result_value, state, version_number,
                 previous_version_id, correction_reason, entered_by, entered_at)
             SELECT :version_id, :order_item_id, :result_value, :state, :version_number,
                    :previous_version_id, :correction_reason, :entered_by, :entered_at
             WHERE EXISTS (
                SELECT 1
                FROM lab_result_versions current
                WHERE current.version_id = :expected_version_id
                  AND current.order_item_id = :current_item_id
                  AND current.state = :pending_state
                  AND current.version_number + 1 = CAST(:next_version_number AS INTEGER)
                  AND current.version_id = :expected_previous_version_id
                  AND current.version_number = (
                    SELECT MAX(version_number)
                    FROM lab_result_versions
                    WHERE order_item_id = :latest_item_id
                  )
             )',
        );
        $parameters['pending_state'] = ResultState::PENDING->value;
        $parameters['expected_previous_version_id'] = $expectedCurrentVersionId;
        $parameters['next_version_number'] = $version->versionNumber;
        $parameters['latest_item_id'] = $version->orderItemId;
        $statement->execute($parameters);

        return $statement->rowCount() === 1;
    }

    /**
     * @return array<string, int|string|null>
     */
    private function parameters(ResultVersion $version): array
    {
        return [
            'version_id' => $version->versionId,
            'order_item_id' => $version->orderItemId,
            'result_value' => $version->value,
            'state' => $version->state->value,
            'version_number' => $version->versionNumber,
            'previous_version_id' => $version->previousVersionId,
            'correction_reason' => $version->correctionReason,
            'entered_by' => $version->enteredBy,
            'entered_at' => $version->enteredAt->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): LabResultRecord
    {
        $version = new ResultVersion(
            (string) $row['version_id'],
            (string) $row['order_item_id'],
            (string) $row['result_value'],
            ResultState::from((string) $row['state']),
            (int) $row['version_number'],
            $row['previous_version_id'] === null ? null : (string) $row['previous_version_id'],
            $row['correction_reason'] === null ? null : (string) $row['correction_reason'],
            (string) $row['entered_by'],
            new DateTimeImmutable((string) $row['entered_at']),
        );

        return $version->state === ResultState::PENDING
            ? new PendingLabResult($version)
            : new ValidatedLabResult($version);
    }
}

<?php

declare(strict_types=1);

namespace LabResults\Persistence;

use LabResults\Application\LabResultRepository;
use LabResults\Domain\LabResultRecord;
use LabResults\Domain\PendingLabResult;

/**
 * Adaptador de producción en memoria: mismo contrato que el adaptador PDO,
 * útil para demostraciones y pruebas sin base de datos.
 */
final class InMemoryLabResultRepository implements LabResultRepository
{
    /** @var array<string, list<LabResultRecord>> */
    private array $history = [];

    /**
     * @param list<LabResultRecord> $initial
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $record) {
            $this->history[$record->version()->orderItemId][] = $record;
        }
    }

    public function currentFor(string $orderItemId): ?LabResultRecord
    {
        $versions = $this->history[$orderItemId] ?? [];

        return $versions === [] ? null : $versions[array_key_last($versions)];
    }

    public function saveInitial(PendingLabResult $result): bool
    {
        $itemId = $result->version()->orderItemId;
        if (isset($this->history[$itemId])) {
            return false;
        }

        $this->history[$itemId][] = $result;

        return true;
    }

    public function appendCorrection(string $expectedCurrentVersionId, PendingLabResult $corrected): bool
    {
        $version = $corrected->version();
        $current = $this->currentFor($version->orderItemId);
        if (
            !$current instanceof PendingLabResult
            || $current->version()->versionId !== $expectedCurrentVersionId
            || $current->version()->versionNumber + 1 !== $version->versionNumber
        ) {
            return false;
        }

        $this->history[$version->orderItemId][] = $corrected;

        return true;
    }

    public function versionCount(string $orderItemId): int
    {
        return count($this->history[$orderItemId] ?? []);
    }
}

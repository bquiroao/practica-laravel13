<?php

declare(strict_types=1);

enum ResultState: string
{
    case PENDING = 'pending';
    case VALIDATED = 'validated';
}

enum OperationStatus: string
{
    case SUCCESS = 'success';
    case REJECTED = 'rejected';
    case CONFLICT = 'conflict';
}

final readonly class ResultVersion
{
    public function __construct(
        public string $versionId,
        public string $orderItemId,
        public string $value,
        public ResultState $state,
        public int $versionNumber,
        public ?string $previousVersionId,
        public ?string $correctionReason,
        public string $enteredBy,
    ) {
        if ($versionId === '' || $orderItemId === '' || $enteredBy === '') {
            throw new InvalidArgumentException('Los identificadores de versión, ítem y autor son obligatorios.');
        }

        if (trim($value) === '') {
            throw new InvalidArgumentException('El valor del resultado no puede estar vacío.');
        }

        if ($versionNumber < 1) {
            throw new InvalidArgumentException('El número de versión debe ser positivo.');
        }

        if ($versionNumber === 1 && ($previousVersionId !== null || $correctionReason !== null)) {
            throw new InvalidArgumentException('La versión inicial no puede referenciar una corrección previa.');
        }

        if ($versionNumber > 1 && (
            $previousVersionId === null
            || $previousVersionId === ''
            || $correctionReason === null
            || trim($correctionReason) === ''
        )) {
            throw new InvalidArgumentException('Una corrección debe enlazar su versión previa y declarar motivo.');
        }
    }
}

interface LabResultRecord
{
    public function version(): ResultVersion;
}

final readonly class PendingLabResult implements LabResultRecord
{
    public function __construct(private ResultVersion $resultVersion)
    {
        if ($resultVersion->state !== ResultState::PENDING) {
            throw new InvalidArgumentException('PendingLabResult requiere una versión pendiente.');
        }
    }

    public function version(): ResultVersion
    {
        return $this->resultVersion;
    }

    public static function capture(
        string $versionId,
        string $orderItemId,
        string $value,
        string $enteredBy,
    ): self {
        return new self(new ResultVersion(
            $versionId,
            $orderItemId,
            $value,
            ResultState::PENDING,
            1,
            null,
            null,
            $enteredBy,
        ));
    }

    public function corrected(
        string $newVersionId,
        string $newValue,
        string $reason,
        string $enteredBy,
    ): self {
        return new self(new ResultVersion(
            $newVersionId,
            $this->resultVersion->orderItemId,
            $newValue,
            ResultState::PENDING,
            $this->resultVersion->versionNumber + 1,
            $this->resultVersion->versionId,
            $reason,
            $enteredBy,
        ));
    }
}

final readonly class ValidatedLabResult implements LabResultRecord
{
    public function __construct(private ResultVersion $resultVersion)
    {
        if ($resultVersion->state !== ResultState::VALIDATED) {
            throw new InvalidArgumentException('ValidatedLabResult requiere una versión validada.');
        }
    }

    public function version(): ResultVersion
    {
        return $this->resultVersion;
    }
}

interface PendingResultRepository
{
    public function currentFor(string $orderItemId): ?LabResultRecord;

    public function saveInitial(PendingLabResult $result): bool;

    public function appendCorrection(
        string $expectedCurrentVersionId,
        PendingLabResult $corrected,
    ): bool;
}

final class InMemoryPendingResultRepository implements PendingResultRepository
{
    /** @var array<string, LabResultRecord> */
    private array $currentByOrderItem = [];

    /**
     * @param list<LabResultRecord> $initialRecords
     */
    public function __construct(array $initialRecords = [])
    {
        foreach ($initialRecords as $record) {
            $orderItemId = $record->version()->orderItemId;

            if (isset($this->currentByOrderItem[$orderItemId])) {
                throw new InvalidArgumentException('Solo se puede hidratar un resultado vigente por ítem.');
            }

            $this->currentByOrderItem[$orderItemId] = $record;
        }
    }

    public function currentFor(string $orderItemId): ?LabResultRecord
    {
        return $this->currentByOrderItem[$orderItemId] ?? null;
    }

    public function saveInitial(PendingLabResult $result): bool
    {
        $orderItemId = $result->version()->orderItemId;

        if (isset($this->currentByOrderItem[$orderItemId])) {
            return false;
        }

        $this->currentByOrderItem[$orderItemId] = $result;
        return true;
    }

    public function appendCorrection(
        string $expectedCurrentVersionId,
        PendingLabResult $corrected,
    ): bool {
        $orderItemId = $corrected->version()->orderItemId;
        $current = $this->currentByOrderItem[$orderItemId] ?? null;

        if (!$current instanceof PendingLabResult) {
            return false;
        }

        if ($current->version()->versionId !== $expectedCurrentVersionId) {
            return false;
        }

        if ($corrected->version()->previousVersionId !== $expectedCurrentVersionId) {
            return false;
        }

        $this->currentByOrderItem[$orderItemId] = $corrected;
        return true;
    }
}

final readonly class OperationResult
{
    private function __construct(
        public OperationStatus $status,
        public ?PendingLabResult $result,
        public ?string $message,
    ) {
    }

    public static function success(PendingLabResult $result): self
    {
        return new self(OperationStatus::SUCCESS, $result, null);
    }

    public static function rejected(string $message): self
    {
        return new self(OperationStatus::REJECTED, null, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(OperationStatus::CONFLICT, null, $message);
    }
}

final class CaptureOrCorrectPendingResult
{
    public function __construct(private PendingResultRepository $repository)
    {
    }

    public function capture(
        string $versionId,
        string $orderItemId,
        string $value,
        string $enteredBy,
    ): OperationResult {
        if (trim($value) === '') {
            return OperationResult::rejected('El valor del resultado es obligatorio.');
        }

        if ($this->repository->currentFor($orderItemId) !== null) {
            return OperationResult::conflict('El ítem ya tiene un resultado vigente.');
        }

        $result = PendingLabResult::capture($versionId, $orderItemId, $value, $enteredBy);

        return $this->repository->saveInitial($result)
            ? OperationResult::success($result)
            : OperationResult::conflict('El resultado cambió antes de completar la captura.');
    }

    public function correct(
        string $orderItemId,
        string $expectedVersionId,
        string $newVersionId,
        string $newValue,
        string $reason,
        string $enteredBy,
    ): OperationResult {
        if (trim($newValue) === '') {
            return OperationResult::rejected('El nuevo valor del resultado es obligatorio.');
        }

        if (trim($reason) === '') {
            return OperationResult::rejected('El motivo de corrección es obligatorio.');
        }

        $current = $this->repository->currentFor($orderItemId);

        if ($current === null) {
            return OperationResult::rejected('No existe un resultado vigente para el ítem.');
        }

        if (!$current instanceof PendingLabResult) {
            return OperationResult::rejected('Solo se pueden corregir resultados pendientes de validación.');
        }

        if ($current->version()->versionId !== $expectedVersionId) {
            return OperationResult::conflict('El resultado cambió; vuelva a cargarlo antes de corregir.');
        }

        $corrected = $current->corrected($newVersionId, $newValue, $reason, $enteredBy);

        return $this->repository->appendCorrection($expectedVersionId, $corrected)
            ? OperationResult::success($corrected)
            : OperationResult::conflict('El resultado dejó de estar pendiente o cambió antes de guardar.');
    }
}

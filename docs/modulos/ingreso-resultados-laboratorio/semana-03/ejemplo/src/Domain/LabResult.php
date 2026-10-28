<?php

declare(strict_types=1);

namespace LabResults\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

enum ResultState: string
{
    case PENDING = 'pending';
    case VALIDATED = 'validated';
}

interface LabResultRecord
{
    public function version(): ResultVersion;
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
        public DateTimeImmutable $enteredAt,
    ) {
        foreach ([
            'identificador de version' => $versionId,
            'item de orden' => $orderItemId,
            'autor' => $enteredBy,
        ] as $field => $valueToCheck) {
            if (trim($valueToCheck) === '') {
                throw new InvalidArgumentException("El {$field} es obligatorio.");
            }
        }

        if (trim($value) === '') {
            throw new InvalidArgumentException('El valor del resultado es obligatorio.');
        }

        if ($versionNumber < 1) {
            throw new InvalidArgumentException('El número de versión debe ser positivo.');
        }

        if ($versionNumber === 1 && ($previousVersionId !== null || $correctionReason !== null)) {
            throw new InvalidArgumentException('La versión inicial no puede tener datos de corrección.');
        }

        if ($versionNumber > 1 && (
            $previousVersionId === null
            || trim($previousVersionId) === ''
            || $correctionReason === null
            || trim($correctionReason) === ''
        )) {
            throw new InvalidArgumentException('La corrección requiere versión previa y motivo.');
        }
    }
}

final readonly class PendingLabResult implements LabResultRecord
{
    public function __construct(private ResultVersion $resultVersion)
    {
        if ($resultVersion->state !== ResultState::PENDING) {
            throw new InvalidArgumentException('PendingLabResult requiere estado pendiente.');
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
        DateTimeImmutable $enteredAt,
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
            $enteredAt,
        ));
    }

    public function corrected(
        string $newVersionId,
        string $newValue,
        string $reason,
        string $enteredBy,
        DateTimeImmutable $enteredAt,
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
            $enteredAt,
        ));
    }
}

final readonly class ValidatedLabResult implements LabResultRecord
{
    public function __construct(private ResultVersion $resultVersion)
    {
        if ($resultVersion->state !== ResultState::VALIDATED) {
            throw new InvalidArgumentException('ValidatedLabResult requiere estado validado.');
        }
    }

    public function version(): ResultVersion
    {
        return $this->resultVersion;
    }
}

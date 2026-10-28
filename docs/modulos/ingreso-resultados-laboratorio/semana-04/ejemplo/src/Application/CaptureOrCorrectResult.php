<?php

declare(strict_types=1);

namespace LabResults\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use LabResults\Domain\LabResultRecord;
use LabResults\Domain\PendingLabResult;

final class CaptureOrCorrectResult
{
    public function __construct(
        private LabResultRepository $repository,
        private ResultIdentifierGenerator $identifierGenerator,
    ) {
    }

    public function capture(string $orderItemId, string $value, string $enteredBy): ResultOperation
    {
        if (($invalid = $this->firstBlank([
            'item de orden' => $orderItemId,
            'valor' => $value,
            'autor' => $enteredBy,
        ])) !== null) {
            return new ResultOperation(OperationStatus::REJECTED, null, $invalid);
        }

        if ($this->repository->currentFor($orderItemId) !== null) {
            return new ResultOperation(OperationStatus::CONFLICT, null, 'El ítem ya tiene un resultado vigente.');
        }

        try {
            $result = PendingLabResult::capture(
                $this->identifierGenerator->generate(),
                $orderItemId,
                $value,
                $enteredBy,
                new DateTimeImmutable(),
            );
        } catch (InvalidArgumentException $exception) {
            return new ResultOperation(OperationStatus::REJECTED, null, $exception->getMessage());
        }

        if (!$this->repository->saveInitial($result)) {
            return new ResultOperation(OperationStatus::CONFLICT, null, 'El resultado cambió antes de guardarse.');
        }

        return new ResultOperation(OperationStatus::CAPTURED, $result, 'Resultado capturado como pendiente.');
    }

    public function correct(
        string $orderItemId,
        string $expectedVersionId,
        string $newValue,
        string $reason,
        string $enteredBy,
    ): ResultOperation {
        if (($invalid = $this->firstBlank([
            'item de orden' => $orderItemId,
            'versión esperada' => $expectedVersionId,
            'valor corregido' => $newValue,
            'motivo de corrección' => $reason,
            'autor' => $enteredBy,
        ])) !== null) {
            return new ResultOperation(OperationStatus::REJECTED, null, $invalid);
        }

        $current = $this->repository->currentFor($orderItemId);
        if ($current === null) {
            return new ResultOperation(OperationStatus::NOT_FOUND, null, 'No existe un resultado vigente para el ítem.');
        }

        $version = $current->version();
        if (!$current instanceof PendingLabResult) {
            return new ResultOperation(OperationStatus::REJECTED, $current, 'Solo se corrigen resultados pendientes.');
        }

        if ($version->versionId !== $expectedVersionId) {
            return new ResultOperation(OperationStatus::CONFLICT, $current, 'La versión cambió; vuelve a cargar el resultado.');
        }

        try {
            $corrected = $current->corrected(
                $this->identifierGenerator->generate(),
                $newValue,
                $reason,
                $enteredBy,
                new DateTimeImmutable(),
            );
        } catch (InvalidArgumentException $exception) {
            return new ResultOperation(OperationStatus::REJECTED, $current, $exception->getMessage());
        }

        if (!$this->repository->appendCorrection($expectedVersionId, $corrected)) {
            return new ResultOperation(OperationStatus::CONFLICT, null, 'La versión dejó de estar pendiente; no se guardó la corrección.');
        }

        return new ResultOperation(OperationStatus::CORRECTED, $corrected, 'Corrección guardada como nueva versión pendiente.');
    }

    private function firstBlank(array $fields): ?string
    {
        foreach ($fields as $name => $value) {
            if (trim($value) === '') {
                return "El {$name} es obligatorio.";
            }
        }

        return null;
    }
}

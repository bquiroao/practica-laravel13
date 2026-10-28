<?php

declare(strict_types=1);

namespace LabResults\Presentation\Cli;

use InvalidArgumentException;
use LabResults\Application\CaptureOrCorrectResult;
use LabResults\Application\OperationStatus;

final class LabResultCommand
{
    public function __construct(private CaptureOrCorrectResult $useCase)
    {
    }

    /**
     * @param array<string, string|false> $options
     */
    public function run(array $options): int
    {
        $action = $options['action'] ?? false;
        if (!is_string($action) || !in_array($action, ['capture', 'correct'], true)) {
            throw new InvalidArgumentException('Usa --action=capture o --action=correct.');
        }

        $orderItemId = $this->required($options, 'item');
        $actor = $this->required($options, 'actor');
        if ($action === 'capture') {
            $operation = $this->useCase->capture($orderItemId, $this->required($options, 'value'), $actor);
        } else {
            $operation = $this->useCase->correct(
                $orderItemId,
                $this->required($options, 'expected'),
                $this->required($options, 'value'),
                $this->required($options, 'reason'),
                $actor,
            );
        }

        echo $operation->message . PHP_EOL;
        if ($operation->result !== null) {
            $version = $operation->result->version();
            echo sprintf(
                "id=%s item=%s version=%d state=%s value=%s entered_by=%s entered_at=%s",
                $version->versionId,
                $version->orderItemId,
                $version->versionNumber,
                $version->state->value,
                $version->value,
                $version->enteredBy,
                $version->enteredAt->format(DATE_ATOM),
            ) . PHP_EOL;
        }

        return match ($operation->status) {
            OperationStatus::CAPTURED, OperationStatus::CORRECTED => 0,
            OperationStatus::REJECTED => 2,
            OperationStatus::CONFLICT => 3,
            OperationStatus::NOT_FOUND => 4,
        };
    }

    /**
     * @param array<string, string|false> $options
     */
    private function required(array $options, string $name): string
    {
        $value = $options[$name] ?? false;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Falta el argumento --{$name}.");
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace LabResults\Presentation\Mvc;

use InvalidArgumentException;
use LabResults\Application\CaptureOrCorrectResult;
use LabResults\Application\OperationStatus;
use LabResults\Application\ResultOperation;

final class InvalidInput extends InvalidArgumentException
{
}

final readonly class Response
{
    public function __construct(public int $exitCode, public string $output)
    {
    }
}

final readonly class CaptureInput
{
    public function __construct(public string $item, public string $value, public string $actor)
    {
    }
}

final readonly class CorrectInput
{
    public function __construct(
        public string $item,
        public string $expected,
        public string $value,
        public string $reason,
        public string $actor,
    ) {
    }
}

/** Valida solo el formato de la entrada; las reglas de negocio viven en Domain/Application. */
final class ResultForm
{
    /**
     * @param array<string, string|false> $options
     */
    public static function capture(array $options): CaptureInput
    {
        return new CaptureInput(
            self::required($options, 'item'),
            self::required($options, 'value'),
            self::required($options, 'actor'),
        );
    }

    /**
     * @param array<string, string|false> $options
     */
    public static function correct(array $options): CorrectInput
    {
        return new CorrectInput(
            self::required($options, 'item'),
            self::required($options, 'expected'),
            self::required($options, 'value'),
            self::required($options, 'reason'),
            self::required($options, 'actor'),
        );
    }

    /**
     * @param array<string, string|false> $options
     */
    private static function required(array $options, string $name): string
    {
        $value = $options[$name] ?? false;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidInput("Falta el argumento --{$name}.");
        }

        return $value;
    }
}

/** Única pieza que decide el texto y el código de salida de la respuesta. */
final class ResultView
{
    public static function operation(ResultOperation $operation): Response
    {
        $output = $operation->message . PHP_EOL;
        if ($operation->result !== null) {
            $version = $operation->result->version();
            $output .= sprintf(
                'id=%s item=%s version=%d state=%s value=%s entered_by=%s entered_at=%s',
                $version->versionId,
                $version->orderItemId,
                $version->versionNumber,
                $version->state->value,
                $version->value,
                $version->enteredBy,
                $version->enteredAt->format(DATE_ATOM),
            ) . PHP_EOL;
        }

        return new Response(match ($operation->status) {
            OperationStatus::CAPTURED, OperationStatus::CORRECTED => 0,
            OperationStatus::REJECTED => 2,
            OperationStatus::CONFLICT => 3,
            OperationStatus::NOT_FOUND => 4,
        }, $output);
    }

    public static function invalidInput(string $message): Response
    {
        return new Response(2, $message . PHP_EOL);
    }
}

/** Coordina formulario, caso de uso y vista. Sin SQL, reglas de negocio ni formato de salida. */
final class ResultController
{
    public function __construct(private CaptureOrCorrectResult $useCase)
    {
    }

    /**
     * @param array<string, string|false> $options
     */
    public function capture(array $options): Response
    {
        try {
            $input = ResultForm::capture($options);
        } catch (InvalidInput $exception) {
            return ResultView::invalidInput($exception->getMessage());
        }

        return ResultView::operation($this->useCase->capture($input->item, $input->value, $input->actor));
    }

    /**
     * @param array<string, string|false> $options
     */
    public function correct(array $options): Response
    {
        try {
            $input = ResultForm::correct($options);
        } catch (InvalidInput $exception) {
            return ResultView::invalidInput($exception->getMessage());
        }

        return ResultView::operation($this->useCase->correct(
            $input->item,
            $input->expected,
            $input->value,
            $input->reason,
            $input->actor,
        ));
    }
}

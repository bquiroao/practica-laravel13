<?php

declare(strict_types=1);

namespace LabResults\Presentation\Cli;

use LabResults\Presentation\Mvc\Response;
use LabResults\Presentation\Mvc\ResultController;
use LabResults\Presentation\Mvc\ResultView;

/** Front controller de consola: resuelve la acción y delega en el controlador. */
final class LabResultCommand
{
    public function __construct(private ResultController $controller)
    {
    }

    /**
     * @param array<string, string|false> $options
     */
    public function handle(array $options): Response
    {
        $action = $options['action'] ?? false;

        return match ($action) {
            'capture' => $this->controller->capture($options),
            'correct' => $this->controller->correct($options),
            default => ResultView::invalidInput('Usa --action=capture o --action=correct.'),
        };
    }

    /**
     * @param array<string, string|false> $options
     */
    public function run(array $options): int
    {
        $response = $this->handle($options);
        echo $response->output;

        return $response->exitCode;
    }
}

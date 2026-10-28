<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Domain/LabResult.php';
require_once __DIR__ . '/src/Application/ResultPorts.php';
require_once __DIR__ . '/src/Application/ResultOperation.php';
require_once __DIR__ . '/src/Application/CaptureOrCorrectResult.php';
require_once __DIR__ . '/src/Infrastructure/Identity/RandomResultIdentifierGenerator.php';
require_once __DIR__ . '/src/Persistence/PdoLabResultRepository.php';
require_once __DIR__ . '/src/Presentation/Cli/LabResultCommand.php';

function createLabResultCommand(): LabResults\Presentation\Cli\LabResultCommand
{
    $databasePath = getenv('LAB_RESULTS_DATABASE') ?: __DIR__ . '/data/lab-results.sqlite';
    $directory = dirname($databasePath);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException("No fue posible crear el directorio de datos: {$directory}");
    }

    $connection = new PDO('sqlite:' . $databasePath);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $connection->exec((string) file_get_contents(__DIR__ . '/database/schema.sql'));

    $repository = new LabResults\Persistence\PdoLabResultRepository($connection);

    return new LabResults\Presentation\Cli\LabResultCommand(
        new LabResults\Application\CaptureOrCorrectResult(
            $repository,
            new LabResults\Infrastructure\Identity\RandomResultIdentifierGenerator(),
        ),
    );
}

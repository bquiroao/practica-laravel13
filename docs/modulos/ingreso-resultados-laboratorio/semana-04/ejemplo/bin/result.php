<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

try {
    $command = createLabResultCommand();
    $options = getopt('', ['action:', 'item:', 'value:', 'actor:', 'expected:', 'reason:']);
    exit($command->run($options));
} catch (\InvalidArgumentException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(2);
} catch (\RuntimeException $exception) {
    fwrite(STDERR, 'Error técnico al procesar el resultado; no se confirmó la operación.' . PHP_EOL);
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

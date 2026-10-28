<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Demostración sin base de datos: captura, corrección y reintento obsoleto sobre el adaptador InMemory.
$command = createLabResultCommand(new LabResults\Persistence\InMemoryLabResultRepository());

$captured = $command->handle(['action' => 'capture', 'item' => 'ITEM-DEMO', 'value' => '5.4', 'actor' => 'TECH-01']);
echo "[captura] exit={$captured->exitCode}" . PHP_EOL . $captured->output;

preg_match('/^id=(\S+)/m', $captured->output, $matches);
$versionId = $matches[1] ?? '';

$corrected = $command->handle([
    'action' => 'correct', 'item' => 'ITEM-DEMO', 'expected' => $versionId,
    'value' => '5.6', 'reason' => 'Corrección de transcripción', 'actor' => 'TECH-02',
]);
echo "[corrección] exit={$corrected->exitCode}" . PHP_EOL . $corrected->output;

$stale = $command->handle([
    'action' => 'correct', 'item' => 'ITEM-DEMO', 'expected' => $versionId,
    'value' => '5.8', 'reason' => 'Versión obsoleta', 'actor' => 'TECH-02',
]);
echo "[obsoleta] exit={$stale->exitCode}" . PHP_EOL . $stale->output;

<?php

declare(strict_types=1);

require_once __DIR__ . '/../../semana-04/ejemplo/bootstrap.php';

use LabResults\Application\CaptureOrCorrectResult;
use LabResults\Application\OperationStatus;
use LabResults\Domain\PendingLabResult;
use LabResults\Persistence\InMemoryLabResultRepository;

$passed = 0;
$failed = 0;

function check(string $label, bool $ok): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

function step(string $title): void
{
    echo PHP_EOL . $title . PHP_EOL;
}

/** Regla propuesta del cambio práctico: valor con forma "número unidad". */
function parseMeasuredValue(string $raw): ?array
{
    if (!preg_match('/^\s*(-?\d+(?:[.,]\d+)?)\s+(\S+)\s*$/u', $raw, $m)) {
        return null;
    }

    return [(float) str_replace(',', '.', $m[1]), $m[2]];
}

/** @param array{low:float,high:float,unit:string}|null $range */
function classifyRange(?array $range, string $rawValue): ?string
{
    $parsed = parseMeasuredValue($rawValue);
    if ($parsed === null) {
        return null;
    }
    [$number, $unit] = $parsed;
    if ($range === null || $range['unit'] !== $unit) {
        return null;
    }

    if ($number < $range['low']) {
        return 'LOW';
    }
    if ($number > $range['high']) {
        return 'HIGH';
    }

    return 'NORMAL';
}

/** @param array<string,array{low:float,high:float,unit:string}> $ranges */
function captureWithPolicy(CaptureOrCorrectResult $useCase, string $item, string $rawValue, string $actor, array $ranges): array
{
    if (trim($item) === '' || trim($actor) === '') {
        return ['accepted' => false, 'reason' => 'INVALID_INPUT'];
    }

    $parsed = parseMeasuredValue($rawValue);
    if ($parsed === null) {
        return ['accepted' => false, 'reason' => 'VALUE_UNIT_REQUIRED'];
    }

    $rangeFlag = classifyRange($ranges[$item] ?? null, $rawValue);
    $capture = $useCase->capture($item, $rawValue, $actor);

    return [
        'accepted' => $capture->status === OperationStatus::CAPTURED,
        'status' => $capture->status->value,
        'reason' => $capture->message,
        'rangeFlag' => $rangeFlag,
        'version' => $capture->result?->version()->versionNumber,
        'state' => $capture->result?->version()->state->value,
    ];
}

final class FixedIds implements LabResults\Application\ResultIdentifierGenerator
{
    private int $counter = 1;

    public function generate(): string
    {
        return 'R' . str_pad((string) $this->counter++, 3, '0', STR_PAD_LEFT);
    }
}

$repo = new InMemoryLabResultRepository();
$useCase = new CaptureOrCorrectResult($repo, new FixedIds());

$ranges = [
    'OI-K' => ['low' => 3.5, 'high' => 5.1, 'unit' => 'mmol/L'],
    'OI-NA' => ['low' => 135.0, 'high' => 145.0, 'unit' => 'mEq/L'],
];

step('A. Punto base del prototipo vigente (Semana 4)');
$currentAccepts = $useCase->capture('OI-LIM', '5.2', 'tec-1');
check(
    'el diseño vigente acepta "5.2" sin unidad (límite real del prototipo)',
    $currentAccepts->status === OperationStatus::CAPTURED
);
check('queda como pendiente en versión 1', $currentAccepts->result?->version()->versionNumber === 1
    && $currentAccepts->result?->version()->state->value === 'pending');

step('B. Cambio práctico defendido (propuesta evaluada)');
$rejectMissingUnit = captureWithPolicy($useCase, 'OI-K', '5.2', 'tec-2', $ranges);
check('la política propuesta rechaza valor sin unidad', $rejectMissingUnit['accepted'] === false
    && $rejectMissingUnit['reason'] === 'VALUE_UNIT_REQUIRED');

$normal = captureWithPolicy($useCase, 'OI-K', '4.8 mmol/L', 'tec-2', $ranges);
check('captura con formato completo se acepta', $normal['accepted'] === true);
check('el rango se clasifica como NORMAL', $normal['rangeFlag'] === 'NORMAL');
check('se conserva corrección controlada: estado pendiente y versión 1', $normal['state'] === 'pending' && $normal['version'] === 1);

$high = captureWithPolicy($useCase, 'OI-NA', '149 mEq/L', 'tec-3', $ranges);
check('captura fuera de rango se acepta pero queda marcada HIGH para revisión', $high['accepted'] === true && $high['rangeFlag'] === 'HIGH');

step('C. Corrección controlada y concurrencia se mantienen');
$current = $repo->currentFor('OI-K');
check('existe resultado vigente para OI-K', $current instanceof PendingLabResult);
$okCorrection = $useCase->correct('OI-K', $current?->version()->versionId ?? '', '4.7 mmol/L', 'Ajuste por control interno', 'tec-2');
check('corrección con versión vigente responde CORRECTED', $okCorrection->status === OperationStatus::CORRECTED);
check('la versión sube a 2 y sigue pendiente', $okCorrection->result?->version()->versionNumber === 2
    && $okCorrection->result?->version()->state->value === 'pending');
$stale = $useCase->correct('OI-K', $current?->version()->versionId ?? '', '4.6 mmol/L', 'Segundo ajuste', 'tec-2');
check('una versión obsoleta responde CONFLICT', $stale->status === OperationStatus::CONFLICT);

echo PHP_EOL . ($passed + $failed) . " comprobaciones, $failed fallidas." . PHP_EOL;
exit($failed === 0 ? 0 : 1);

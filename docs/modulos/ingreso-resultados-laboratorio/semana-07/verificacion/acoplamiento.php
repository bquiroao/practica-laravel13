<?php

declare(strict_types=1);

require_once __DIR__ . '/../../semana-04/ejemplo/bootstrap.php';
require_once __DIR__ . '/../despues/Refactor.php';

use LabResults\Domain\ResultState;
use LabResults\Domain\ResultVersion;
use LabResults\Domain\ValidatedLabResult;
use LabResults\Persistence\InMemoryLabResultRepository;
use LabResults\Refactor\CaptureCommand;
use LabResults\Refactor\Clock;
use LabResults\Refactor\CorrectCommand;
use LabResults\Refactor\EntryService;
use LabResults\Refactor\OutcomeView;
use LabResults\Refactor\RangeFlag;
use LabResults\Refactor\Reason;
use LabResults\Refactor\ReferenceRange;
use LabResults\Refactor\ReferenceRangeProvider;

$passed = 0;
$failed = 0;
function check(string $name, bool $ok): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

$before = file_get_contents(__DIR__ . '/../../semana-04/ejemplo/src/Application/CaptureOrCorrectResult.php');
$after = file_get_contents(__DIR__ . '/../despues/Refactor.php');

preg_match_all('/function\s+(capture|correct)\s*\(([^)]*)\)/', $before, $mBefore);
$beforeParams = 0;
foreach ($mBefore[2] as $signature) {
    $beforeParams += substr_count($signature, '$');
}
preg_match_all('/function\s+(capture|correct)\s*\(([^)]*)\)/', $after, $mAfter);
$afterParams = 0;
foreach ($mAfter[2] as $signature) {
    $afterParams += substr_count($signature, '$');
}

$beforeUi = preg_match_all('/new\s+ResultOperation\([^\)]*\'[^\']*\s[^\']*\'/m', $before);
$entryBlock = '';
if (preg_match('/final class EntryService\s*\{(.*)\n\}\n\n\/\*\s*---------- Presentation/s', $after, $block) === 1) {
    $entryBlock = $block[1];
}
$afterUi = preg_match_all('/\'[^\']*\s[^\']*\'/m', $entryBlock);
$beforeValueRange = preg_match_all('/\b(unit|range|rango|unidad)\b/iu', $before);
$afterValueRange = preg_match_all('/\b(ResultValue|ReferenceRange|RangeFlag)\b/', $after);

printf("Medidas antes/después del punto de mayor acoplamiento:\n");
printf("  Parámetros escalares en capture/correct .... %d -> %d\n", $beforeParams, $afterParams);
printf("  Mensajes de interfaz dentro de aplicación ... %d -> %d\n", $beforeUi, $afterUi);
printf("  Tipos explícitos valor/unidad/rango ......... %d -> %d\n\n", $beforeValueRange, $afterValueRange);

check('la entrada se encapsula en comandos (menos parámetros en el caso de uso)', $beforeParams > $afterParams);
check('la aplicación deja de construir mensajes de UI', $beforeUi > $afterUi);
check('el refactor crea tipos de valor, unidad y rango en dominio', $afterValueRange >= 6);

final class SeqIds implements LabResults\Application\ResultIdentifierGenerator
{
    private int $i = 1;
    public function generate(): string
    {
        return 'V-' . $this->i++;
    }
}

final class FixedClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-27T12:00:00-06:00');
    }
}

final class FakeRanges implements ReferenceRangeProvider
{
    public function __construct(private array $map) {}
    public function forItem(string $orderItemId): ?ReferenceRange
    {
        return $this->map[$orderItemId] ?? null;
    }
}

$repo = new InMemoryLabResultRepository([
    new ValidatedLabResult(new ResultVersion(
        versionId: 'VAL-1',
        orderItemId: 'item-validado',
        value: '5.2 mmol/L',
        state: ResultState::VALIDATED,
        versionNumber: 1,
        previousVersionId: null,
        correctionReason: null,
        enteredBy: 'bioq-9',
        enteredAt: new DateTimeImmutable('2026-10-26T08:00:00-06:00'),
    )),
]);

$service = new EntryService(
    $repo,
    new SeqIds(),
    new FixedClock(),
    new FakeRanges([
        'item-a' => new ReferenceRange(4.0, 6.0, 'mmol/L'),
        'item-b' => new ReferenceRange(130.0, 145.0, 'mEq/L'),
    ]),
);

echo PHP_EOL . "Pruebas del refactor propuesto:" . PHP_EOL;

$cap = $service->capture(new CaptureCommand('item-a', '5.5 mmol/L', 'tec-1'));
check('captura válida responde CAPTURED', $cap->reason === Reason::CAPTURED);
check('la bandera de rango se calcula en NORMAL', $cap->flag === RangeFlag::NORMAL);
check('el valor queda canónico en el agregado', $cap->result?->version()->value === '5.5 mmol/L');
check('la vista mapea éxito con código 0', OutcomeView::exitCode($cap) === 0);

$high = $service->capture(new CaptureCommand('item-b', '149 mEq/L', 'tec-1'));
check('captura fuera de rango alto marca HIGH', $high->reason === Reason::CAPTURED && $high->flag === RangeFlag::HIGH);
check('la vista agrega alerta de rango en el mensaje', str_contains(OutcomeView::message($high), 'fuera del rango'));

$fmt = $service->capture(new CaptureCommand('item-z', 'valor sin unidad', 'tec-1'));
check('formato inválido se rechaza por INVALID_INPUT', $fmt->reason === Reason::INVALID_INPUT && $fmt->field === 'value');
check('la vista mapea INVALID_INPUT al código 2', OutcomeView::exitCode($fmt) === 2);

$unit = $service->capture(new CaptureCommand('item-a', '5.2 mg/dL', 'tec-1'));
check('unidad distinta del rango se rechaza con UNIT_MISMATCH', $unit->reason === Reason::UNIT_MISMATCH);

$dup = $service->capture(new CaptureCommand('item-a', '5.3 mmol/L', 'tec-2'));
check('capturar dos veces el mismo ítem responde ITEM_HAS_RESULT', $dup->reason === Reason::ITEM_HAS_RESULT);

$current = $cap->result?->version()->versionId ?? '';
$cor = $service->correct(new CorrectCommand('item-a', $current, '5.1 mmol/L', 'ajuste decimal', 'tec-3'));
check('corregir con versión vigente responde CORRECTED', $cor->reason === Reason::CORRECTED);
check('la corrección queda en versión 2 y sigue pendiente', $cor->result?->version()->versionNumber === 2 && $cor->result?->version()->state === ResultState::PENDING);

$stale = $service->correct(new CorrectCommand('item-a', $current, '5.0 mmol/L', 'nuevo ajuste', 'tec-3'));
check('corregir con versión obsoleta responde STALE_VERSION', $stale->reason === Reason::STALE_VERSION);

$validated = $service->correct(new CorrectCommand('item-validado', 'VAL-1', '5.1 mmol/L', 'cambio tardío', 'tec-3'));
check('un resultado validado no se corrige (ALREADY_VALIDATED)', $validated->reason === Reason::ALREADY_VALIDATED);

$missing = $service->correct(new CorrectCommand('item-x', 'V-99', '5.1 mmol/L', 'no existe', 'tec-3'));
check('corregir un ítem inexistente responde NOT_FOUND', $missing->reason === Reason::NOT_FOUND);

$blank = $service->correct(new CorrectCommand('item-a', 'V-2', '5.1 mmol/L', ' ', 'tec-3'));
check('rechazo de entrada en blanco identifica el campo', $blank->reason === Reason::INVALID_INPUT && $blank->field === 'reason');

echo PHP_EOL . ($passed + $failed) . " comprobaciones, $failed fallidas." . PHP_EOL;
exit($failed === 0 ? 0 : 1);

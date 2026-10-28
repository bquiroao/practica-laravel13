<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use LabResults\Application\CaptureOrCorrectResult;
use LabResults\Application\LabResultRepository;
use LabResults\Application\OperationStatus;
use LabResults\Application\ResultIdentifierGenerator;
use LabResults\Domain\LabResultRecord;
use LabResults\Domain\PendingLabResult;
use LabResults\Domain\ResultState;
use LabResults\Domain\ResultVersion;
use LabResults\Domain\ValidatedLabResult;
use LabResults\Persistence\PdoLabResultRepository;

final class FixedResultIdentifierGenerator implements ResultIdentifierGenerator
{
    private int $next = 0;

    public function generate(): string
    {
        return 'RES-TEST-' . ++$this->next;
    }
}

final class InMemoryLabResultRepository implements LabResultRepository
{
    /** @var array<string, list<LabResultRecord>> */
    public array $history = [];

    public bool $failNextWrite = false;
    public bool $conflictNextWrite = false;

    /**
     * @param list<LabResultRecord> $initial
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $record) {
            $itemId = $record->version()->orderItemId;
            $this->history[$itemId][] = $record;
        }
    }

    public function currentFor(string $orderItemId): ?LabResultRecord
    {
        $versions = $this->history[$orderItemId] ?? [];

        return $versions === [] ? null : $versions[array_key_last($versions)];
    }

    public function saveInitial(PendingLabResult $result): bool
    {
        if ($this->failNextWrite) {
            $this->failNextWrite = false;
            throw new RuntimeException('Fallo de persistencia simulado.');
        }

        $itemId = $result->version()->orderItemId;
        if (isset($this->history[$itemId]) || $this->conflictNextWrite) {
            $this->conflictNextWrite = false;
            return false;
        }

        $this->history[$itemId][] = $result;

        return true;
    }

    public function appendCorrection(string $expectedCurrentVersionId, PendingLabResult $corrected): bool
    {
        if ($this->failNextWrite) {
            $this->failNextWrite = false;
            throw new RuntimeException('Fallo de persistencia simulado.');
        }

        if ($this->conflictNextWrite) {
            $this->conflictNextWrite = false;
            return false;
        }

        $itemId = $corrected->version()->orderItemId;
        $current = $this->currentFor($itemId);
        if (
            !$current instanceof PendingLabResult
            || $current->version()->versionId !== $expectedCurrentVersionId
        ) {
            return false;
        }

        $this->history[$itemId][] = $corrected;

        return true;
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }
    echo "PASS: {$message}" . PHP_EOL;
};

$repository = new InMemoryLabResultRepository();
$useCase = new CaptureOrCorrectResult($repository, new FixedResultIdentifierGenerator());

$captured = $useCase->capture('ITEM-001', '5.4', 'TECH-01');
$assert($captured->status === OperationStatus::CAPTURED, 'captura una versión inicial pendiente');
$assert($captured->result?->version()->versionNumber === 1, 'la captura inicia en versión uno');
$assert($captured->result?->version()->state === ResultState::PENDING, 'la captura no se considera validada');

$corrected = $useCase->correct('ITEM-001', 'RES-TEST-1', '5.6', 'Corrección de transcripción', 'TECH-02');
$assert($corrected->status === OperationStatus::CORRECTED, 'corrige resultado que sigue pendiente');
$assert($corrected->result?->version()->versionNumber === 2, 'la corrección crea versión dos');
$assert($corrected->result?->version()->previousVersionId === 'RES-TEST-1', 'la nueva versión enlaza la anterior');
$assert($corrected->result?->version()->state === ResultState::PENDING, 'la versión corregida sigue pendiente');
$assert(count($repository->history['ITEM-001']) === 2, 'el historial conserva ambas versiones');

$blank = $useCase->capture('ITEM-002', '  ', 'TECH-01');
$assert($blank->status === OperationStatus::REJECTED, 'rechaza valor vacío sin persistir');
$missingReason = $useCase->correct('ITEM-001', 'RES-TEST-2', '5.7', ' ', 'TECH-02');
$assert($missingReason->status === OperationStatus::REJECTED, 'rechaza corrección sin motivo');

$stale = $useCase->correct('ITEM-001', 'RES-TEST-1', '5.8', 'Versión obsoleta', 'TECH-02');
$assert($stale->status === OperationStatus::CONFLICT, 'rechaza corrección basada en versión obsoleta');
$assert(count($repository->history['ITEM-001']) === 2, 'el conflicto no cambia el historial');

$validated = new ValidatedLabResult(new ResultVersion(
    'RES-VALIDATED',
    'ITEM-003',
    '7.1',
    ResultState::VALIDATED,
    1,
    null,
    null,
    'TECH-01',
    new DateTimeImmutable(),
));
$validatedRepository = new InMemoryLabResultRepository([$validated]);
$validatedUseCase = new CaptureOrCorrectResult($validatedRepository, new FixedResultIdentifierGenerator());
$validatedCorrection = $validatedUseCase->correct('ITEM-003', 'RES-VALIDATED', '7.2', 'No permitido', 'TECH-02');
$assert($validatedCorrection->status === OperationStatus::REJECTED, 'no permite corregir un resultado validado');

$writeFailureRepository = new InMemoryLabResultRepository();
$writeFailureRepository->failNextWrite = true;
$writeFailureUseCase = new CaptureOrCorrectResult($writeFailureRepository, new FixedResultIdentifierGenerator());
$writeFailed = false;
try {
    $writeFailureUseCase->capture('ITEM-004', '3.2', 'TECH-01');
} catch (RuntimeException $exception) {
    $writeFailed = $exception->getMessage() === 'Fallo de persistencia simulado.';
}
$assert($writeFailed, 'propaga el error de persistencia sin éxito simulado');
$assert($writeFailureRepository->currentFor('ITEM-004') === null, 'fallo de persistencia no crea resultado');

$racingRepository = new InMemoryLabResultRepository();
$racingUseCase = new CaptureOrCorrectResult($racingRepository, new FixedResultIdentifierGenerator());
$racingUseCase->capture('ITEM-005', '2.1', 'TECH-01');
$racingRepository->conflictNextWrite = true;
$race = $racingUseCase->correct('ITEM-005', 'RES-TEST-1', '2.2', 'Control de concurrencia', 'TECH-02');
$assert($race->status === OperationStatus::CONFLICT, 'informa conflicto si el append condicional no se aplica');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec((string) file_get_contents(__DIR__ . '/../database/schema.sql'));
$pdoRepository = new PdoLabResultRepository($pdo);
$pdoUseCase = new CaptureOrCorrectResult($pdoRepository, new FixedResultIdentifierGenerator());
$pdoCapture = $pdoUseCase->capture('ITEM-PDO-01', '4.2', 'TECH-PDO-01');
$assert($pdoCapture->status === OperationStatus::CAPTURED, 'PDO SQLite guarda la captura inicial');
$pdoVersionId = $pdoCapture->result?->version()->versionId ?? '';
$pdoCorrection = $pdoUseCase->correct(
    'ITEM-PDO-01',
    $pdoVersionId,
    '4.3',
    'Corrección de dato sintético',
    'TECH-PDO-02',
);
$assert(
    $pdoCorrection->status === OperationStatus::CORRECTED,
    'PDO SQLite anexa la corrección: ' . $pdoCorrection->message,
);
$pdoCurrent = $pdoRepository->currentFor('ITEM-PDO-01');
$assert($pdoCurrent?->version()->versionNumber === 2, 'PDO SQLite devuelve la versión corregida como vigente');
$assert($pdoCurrent?->version()->previousVersionId === $pdoVersionId, 'PDO SQLite conserva el vínculo de versiones');
$pdoStaleCorrection = $pdoUseCase->correct(
    'ITEM-PDO-01',
    $pdoVersionId,
    '4.4',
    'Reintento sintético obsoleto',
    'TECH-PDO-02',
);
$assert($pdoStaleCorrection->status === OperationStatus::CONFLICT, 'PDO SQLite rechaza append con versión obsoleta');
$pdoHistoryCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM lab_result_versions WHERE order_item_id = 'ITEM-PDO-01'",
)->fetchColumn();
$assert($pdoHistoryCount === 2, 'conflicto PDO SQLite deja intactas ambas versiones');

echo sprintf("OK: %d aserciones.%s", $assertions, PHP_EOL);

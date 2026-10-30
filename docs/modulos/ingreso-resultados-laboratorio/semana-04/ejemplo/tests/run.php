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
use LabResults\Persistence\InMemoryLabResultRepository;
use LabResults\Persistence\PdoLabResultRepository;
use LabResults\Presentation\Cli\LabResultCommand;
use LabResults\Presentation\Mvc\ResultController;

final class FixedResultIdentifierGenerator implements ResultIdentifierGenerator
{
    private int $next = 0;

    public function generate(): string
    {
        return 'RES-TEST-' . ++$this->next;
    }
}

/** Decorador de pruebas sobre un adaptador real: inyecta fallos y conflictos sin duplicar lógica. */
final class FaultInjectingRepository implements LabResultRepository
{
    public bool $failNextWrite = false;
    public bool $conflictNextWrite = false;

    public function __construct(private LabResultRepository $inner)
    {
    }

    public function currentFor(string $orderItemId): ?LabResultRecord
    {
        return $this->inner->currentFor($orderItemId);
    }

    public function saveInitial(PendingLabResult $result): bool
    {
        return $this->guard() && $this->inner->saveInitial($result);
    }

    public function appendCorrection(string $expectedCurrentVersionId, PendingLabResult $corrected): bool
    {
        return $this->guard() && $this->inner->appendCorrection($expectedCurrentVersionId, $corrected);
    }

    private function guard(): bool
    {
        if ($this->failNextWrite) {
            $this->failNextWrite = false;
            throw new RuntimeException('Fallo de persistencia simulado.');
        }
        if ($this->conflictNextWrite) {
            $this->conflictNextWrite = false;
            return false;
        }

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

$validatedRecord = static fn (string $item): ValidatedLabResult => new ValidatedLabResult(new ResultVersion(
    'RES-VALIDATED-' . $item,
    $item,
    '7.1',
    ResultState::VALIDATED,
    1,
    null,
    null,
    'TECH-01',
    new DateTimeImmutable(),
));

// --- Suite de contrato: se ejecuta igual contra cada adaptador del puerto LabResultRepository ---
$adapters = [
    'InMemory' => static fn (array $initial): LabResultRepository => new InMemoryLabResultRepository($initial),
    'PDO SQLite' => static function (array $initial): LabResultRepository {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec((string) file_get_contents(__DIR__ . '/../database/schema.sql'));
        $insert = $pdo->prepare(
            'INSERT INTO lab_result_versions
                (version_id, order_item_id, result_value, state, version_number,
                 previous_version_id, correction_reason, entered_by, entered_at)
             VALUES (:id, :item, :value, :state, :number, NULL, NULL, :by, :at)',
        );
        foreach ($initial as $record) {
            $v = $record->version();
            $insert->execute([
                'id' => $v->versionId, 'item' => $v->orderItemId, 'value' => $v->value,
                'state' => $v->state->value, 'number' => $v->versionNumber,
                'by' => $v->enteredBy, 'at' => $v->enteredAt->format(DATE_ATOM),
            ]);
        }

        return new PdoLabResultRepository($pdo);
    },
];

foreach ($adapters as $name => $factory) {
    $repository = $factory([$validatedRecord('ITEM-V')]);
    $now = new DateTimeImmutable();
    $first = PendingLabResult::capture('V1', 'ITEM-C', '1.0', 'TECH-01', $now);

    $assert($repository->currentFor('ITEM-C') === null, "[{$name}] sin historial devuelve null");
    $assert($repository->saveInitial($first), "[{$name}] guarda la versión inicial");
    $assert(!$repository->saveInitial(PendingLabResult::capture('V1B', 'ITEM-C', '1.1', 'TECH-01', $now)), "[{$name}] no duplica la versión inicial");
    $assert($repository->currentFor('ITEM-C')?->version()->versionId === 'V1', "[{$name}] la inicial conservada es la primera");

    $second = $first->corrected('V2', '1.2', 'Motivo', 'TECH-02', $now);
    $assert($repository->appendCorrection('V1', $second), "[{$name}] anexa corrección sobre la versión vigente");
    $assert($repository->currentFor('ITEM-C')?->version()->versionNumber === 2, "[{$name}] la vigente es la versión dos");
    $assert(!$repository->appendCorrection('V1', $first->corrected('V2X', '1.3', 'Obsoleta', 'TECH-02', $now)), "[{$name}] rechaza corrección con versión obsoleta");
    $assert($repository->currentFor('ITEM-C')?->version()->versionId === 'V2', "[{$name}] el rechazo no altera el historial");

    $validated = $repository->currentFor('ITEM-V');
    $assert($validated instanceof ValidatedLabResult, "[{$name}] recupera un resultado validado");
    $fake = PendingLabResult::capture('VX', 'ITEM-V', '7.0', 'TECH-01', $now)
        ->corrected('VY', '7.2', 'No permitido', 'TECH-02', $now);
    $assert(!$repository->appendCorrection('RES-VALIDATED-ITEM-V', $fake), "[{$name}] no anexa sobre un resultado validado");
}

// --- Caso de uso (reglas) sobre el adaptador InMemory real ---
$repository = new InMemoryLabResultRepository();
$useCase = new CaptureOrCorrectResult($repository, new FixedResultIdentifierGenerator());

$captured = $useCase->capture('ITEM-001', '5.4', 'TECH-01');
$assert($captured->status === OperationStatus::CAPTURED, 'captura una versión inicial pendiente');
$assert($captured->result?->version()->state === ResultState::PENDING, 'la captura no se considera validada');
$corrected = $useCase->correct('ITEM-001', 'RES-TEST-1', '5.6', 'Corrección de transcripción', 'TECH-02');
$assert($corrected->status === OperationStatus::CORRECTED, 'corrige resultado que sigue pendiente');
$assert($corrected->result?->version()->previousVersionId === 'RES-TEST-1', 'la nueva versión enlaza la anterior');
$assert($repository->versionCount('ITEM-001') === 2, 'el historial conserva ambas versiones');
$assert($useCase->capture('ITEM-002', '  ', 'TECH-01')->status === OperationStatus::REJECTED, 'rechaza valor vacío');
$assert($useCase->correct('ITEM-001', 'RES-TEST-2', '5.7', ' ', 'TECH-02')->status === OperationStatus::REJECTED, 'rechaza corrección sin motivo');
$assert($useCase->correct('ITEM-001', 'RES-TEST-1', '5.8', 'Obsoleta', 'TECH-02')->status === OperationStatus::CONFLICT, 'rechaza versión obsoleta');

$validatedUseCase = new CaptureOrCorrectResult(new InMemoryLabResultRepository([$validatedRecord('ITEM-003')]), new FixedResultIdentifierGenerator());
$assert($validatedUseCase->correct('ITEM-003', 'RES-VALIDATED-ITEM-003', '7.2', 'No permitido', 'TECH-02')->status === OperationStatus::REJECTED, 'no permite corregir un resultado validado');

$faulty = new FaultInjectingRepository(new InMemoryLabResultRepository());
$faultyUseCase = new CaptureOrCorrectResult($faulty, new FixedResultIdentifierGenerator());
$faulty->failNextWrite = true;
$writeFailed = false;
try {
    $faultyUseCase->capture('ITEM-004', '3.2', 'TECH-01');
} catch (RuntimeException $exception) {
    $writeFailed = $exception->getMessage() === 'Fallo de persistencia simulado.';
}
$assert($writeFailed && $faulty->currentFor('ITEM-004') === null, 'propaga el error de persistencia sin crear resultado');
$faultyUseCase->capture('ITEM-005', '2.1', 'TECH-01');
$faulty->conflictNextWrite = true;
$assert($faultyUseCase->correct('ITEM-005', 'RES-TEST-1', '2.2', 'Concurrencia', 'TECH-02')->status === OperationStatus::CONFLICT, 'informa conflicto si el append condicional no se aplica');

// --- MVC: el controlador funciona solo, sin Kernel ni base de datos ---
$controller = new ResultController(new CaptureOrCorrectResult(new InMemoryLabResultRepository(), new FixedResultIdentifierGenerator()));
$ok = $controller->capture(['item' => 'ITEM-M', 'value' => '9.9', 'actor' => 'TECH-01']);
$assert($ok->exitCode === 0 && str_contains($ok->output, 'state=pending'), 'MVC: captura devuelve código 0 y vista con estado pendiente');
$assert($controller->capture(['item' => 'ITEM-N', 'actor' => 'TECH-01'])->exitCode === 2, 'MVC: formulario rechaza entrada sin valor (código 2)');
$assert($controller->capture(['item' => 'ITEM-M', 'value' => '1', 'actor' => 'TECH-01'])->exitCode === 3, 'MVC: duplicado se presenta como conflicto (código 3)');
$assert($controller->correct(['item' => 'ITEM-X', 'expected' => 'Z', 'value' => '1', 'reason' => 'r', 'actor' => 'T'])->exitCode === 4, 'MVC: ítem inexistente se presenta como no encontrado (código 4)');
$front = new LabResultCommand($controller);
$assert($front->handle(['action' => 'otra'])->exitCode === 2, 'front controller rechaza acciones desconocidas');
$assert($front->handle(['action' => 'correct', 'item' => 'ITEM-M', 'expected' => 'RES-TEST-1', 'value' => '9.8', 'reason' => 'Ajuste', 'actor' => 'TECH-02'])->exitCode === 0, 'front controller enruta la corrección');

// --- Arquitectura: reglas verificadas leyendo el código fuente ---
$src = __DIR__ . '/../src';
$read = static fn (string $path): string => (string) file_get_contents($path);
$stripComments = static fn (string $code): string => (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $code);
$violations = static function (string $code, array $patterns): array {
    return array_values(array_filter($patterns, static fn (string $p): bool => preg_match($p, $code) === 1));
};

$controllerSource = $stripComments($read($src . '/Presentation/Mvc/Mvc.php'));
preg_match('/final class ResultController.*\z/s', $controllerSource, $match);
$controllerCode = $match[0] ?? '';
$controllerRules = [
    '/\bPDO\b/', '/\b(SELECT|INSERT|UPDATE|DELETE)\b/', '/LabResults\\\\Domain/', '/LabResults\\\\Persistence/',
    '/\bnew\s+[A-Z]/', '/\becho\b|\bprint\b/', '/PHP_EOL/',
];
$assert($controllerCode !== '' && $violations($controllerCode, $controllerRules) === [], 'ARQ-01 el controlador no tiene SQL, PDO, dominio, persistencia, new ni formato de salida');
$assert($violations('$pdo->query("SELECT 1");', $controllerRules) !== [], 'ARQ-01 el detector sí encuentra una violación (control negativo)');

$applicationRules = ['/\bPDO\b/', '/LabResults\\\\Persistence/', '/LabResults\\\\Presentation/'];
foreach (glob($src . '/Application/*.php') as $file) {
    $assert($violations($stripComments($read($file)), $applicationRules) === [], 'ARQ-02 Application no depende de PDO, Persistence ni Presentation: ' . basename($file));
}
$domainRules = ['/\bPDO\b/', '/LabResults\\\\(Application|Persistence|Presentation|Infrastructure)/'];
$assert($violations($stripComments($read($src . '/Domain/LabResult.php')), $domainRules) === [], 'ARQ-03 Domain no depende de otras capas');
$assert(preg_match('/interface LabResultRepository/', $read($src . '/Application/ResultPorts.php')) === 1, 'ARQ-04 la interfaz Repository vive en el límite de Application');
foreach (['Pdo', 'InMemory'] as $adapter) {
    $assert(preg_match('/class ' . $adapter . 'LabResultRepository implements LabResultRepository/', $read($src . "/Persistence/{$adapter}LabResultRepository.php")) === 1, "ARQ-05 el adaptador {$adapter} implementa el puerto");
}

echo sprintf('OK: %d aserciones.%s', $assertions, PHP_EOL);

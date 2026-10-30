<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

final class Week04FixedAuthorization implements ReviewerAuthorization
{
    public function __construct(private bool $allowed = true)
    {
    }

    public function canReview(string $actorId, string $hospitalId): bool
    {
        return $this->allowed;
    }
}

final class Week04FixedClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-27T12:00:00-06:00');
    }
}

final class Week04RaceRepository implements LabResultDecisionRepository
{
    public function __construct(private InMemoryLabResultDecisionRepository $inner)
    {
    }

    public function findById(string $resultId): ?LabResult
    {
        $snapshot = $this->inner->findById($resultId);
        if ($snapshot !== null) {
            $this->inner->seed(new LabResult(
                $snapshot->resultId,
                $snapshot->hospitalId,
                $snapshot->orderItemId,
                $snapshot->examinationId,
                $snapshot->specimenId,
                $snapshot->version + 1,
                '5.6',
                $snapshot->unit,
                LabResultStatus::PENDING,
            ));
        }

        return $snapshot;
    }

    public function decideAtomically(ReviewDecisionRecord $record): DecisionWriteStatus
    {
        return $this->inner->decideAtomically($record);
    }
}

final class Week04Checks
{
    private int $assertions = 0;
    private int $failures = 0;

    public function same(mixed $expected, mixed $actual, string $message): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            $this->failures++;
            echo 'FAIL: ' . $message . "\n";

            return;
        }

        echo 'PASS: ' . $message . "\n";
    }

    public function finish(): int
    {
        echo "\n{$this->assertions} comprobaciones, {$this->failures} fallidas.\n";

        return $this->failures === 0 ? 0 : 1;
    }
}

function week04Result(
    string $resultId,
    string $hospitalId = 'H-1',
    int $version = 1,
    LabResultStatus $status = LabResultStatus::PENDING,
    string $value = '5.4',
): LabResult {
    return new LabResult(
        $resultId,
        $hospitalId,
        'ORDER-ITEM-1',
        'EXAM-1',
        'SPECIMEN-1',
        $version,
        $value,
        'mmol/L',
        $status,
    );
}

function week04Workflow(
    LabResultDecisionRepository $repository,
    bool $authorized = true,
): ReviewPendingLabResult {
    return new ReviewPendingLabResult(
        new Week04FixedAuthorization($authorized),
        new Week04FixedClock(),
        $repository,
    );
}

function week04Router(ReviewPendingLabResult $workflow): ReviewRouter
{
    return new ReviewRouter(new ReviewController($workflow, new ReviewJsonView()));
}

$checks = new Week04Checks();

$memory = new InMemoryLabResultDecisionRepository();
$memory->seed(week04Result('M-1'));
$memoryOutcome = week04Workflow($memory)->execute(new ReviewCommand(
    'M-1',
    1,
    'BIO-1',
    'H-1',
    ReviewDecision::VALIDATE,
));
$checks->same(ReviewOutcomeStatus::VALIDATED, $memoryOutcome->status, 'Repository InMemory guarda la validación');
$checks->same('5.4', $memory->findById('M-1')?->value, 'validar conserva el valor analítico');
$checks->same('BIO-1', $memory->decisions()[0]->actorId, 'el repositorio conserva actor y auditoría');

$memory = new InMemoryLabResultDecisionRepository();
$memory->seed(week04Result('M-2'));
$response = week04Router(week04Workflow($memory))->dispatch(new ReviewHttpRequest(
    'POST',
    '/results/M-2/decision',
    ['expectedVersion' => 1, 'decision' => 'REJECT', 'reason' => 'Muestra por confirmar'],
    'BIO-1',
    'H-1',
));
$checks->same(200, $response->statusCode, 'MVC devuelve 200 para rechazo persistido');
$checks->same('REJECTED', $response->body['status'], 'la vista representa el rechazo');
$checks->same('Muestra por confirmar', $response->body['decision']['reason'], 'MVC retorna el motivo de auditoría');

$invalid = week04Router(week04Workflow(new InMemoryLabResultDecisionRepository()))->dispatch(
    new ReviewHttpRequest('POST', '/results/M-3/decision', ['expectedVersion' => 1, 'decision' => 'OTHER'], 'BIO-1', 'H-1'),
);
$checks->same(422, $invalid->statusCode, 'MVC rechaza un contrato de entrada inválido');

$wrongMethod = week04Router(week04Workflow(new InMemoryLabResultDecisionRepository()))->dispatch(
    new ReviewHttpRequest('GET', '/results/M-3/decision', [], 'BIO-1', 'H-1'),
);
$checks->same(405, $wrongMethod->statusCode, 'router MVC rechaza métodos no permitidos');

$unknownRoute = week04Router(week04Workflow(new InMemoryLabResultDecisionRepository()))->dispatch(
    new ReviewHttpRequest('POST', '/unknown', [], 'BIO-1', 'H-1'),
);
$checks->same(404, $unknownRoute->statusCode, 'router MVC informa rutas desconocidas');

$deniedMemory = new InMemoryLabResultDecisionRepository();
$deniedMemory->seed(week04Result('M-4'));
$denied = week04Router(week04Workflow($deniedMemory, false))->dispatch(new ReviewHttpRequest(
    'POST',
    '/results/M-4/decision',
    ['expectedVersion' => 1, 'decision' => 'VALIDATE'],
    'OTHER',
    'H-1',
));
$checks->same(403, $denied->statusCode, 'la vista mapea denegación a 403');
$checks->same([], $deniedMemory->decisions(), 'la denegación no persiste cambios');

$racing = new InMemoryLabResultDecisionRepository();
$racing->seed(week04Result('M-5'));
$race = week04Router(week04Workflow(new Week04RaceRepository($racing)))->dispatch(new ReviewHttpRequest(
    'POST',
    '/results/M-5/decision',
    ['expectedVersion' => 1, 'decision' => 'VALIDATE'],
    'BIO-1',
    'H-1',
));
$checks->same(409, $race->statusCode, 'MVC mapea conflicto de versión a 409');
$checks->same('5.6', $racing->findById('M-5')?->value, 'la carrera conserva la versión actualizada');

$mvcSource = (string) file_get_contents(__DIR__ . '/../src/Presentation/Mvc.php');
$controllerStart = strpos($mvcSource, 'final class ReviewController');
$routerStart = strpos($mvcSource, 'final class ReviewRouter');
$controllerSource = $controllerStart !== false && $routerStart !== false
    ? substr($mvcSource, $controllerStart, $routerStart - $controllerStart)
    : '';
$checks->same(true, $controllerSource !== '', 'la implementación MVC contiene un controlador identificable');
$checks->same(
    0,
    preg_match('/\b(PDO|PDOException|SELECT|INSERT|UPDATE|DELETE)\b/i', $controllerSource),
    'el controlador no depende de PDO ni contiene SQL',
);
$checks->same(
    0,
    preg_match('/ReviewDecision::REJECT|ReviewDecision::VALIDATE/', $controllerSource),
    'el controlador no codifica reglas de decisión clínica',
);

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec((string) file_get_contents(__DIR__ . '/../database/schema.sql'));
    $pdo->exec(
        "INSERT INTO lab_result "
        . "(id, hospital_id, order_item_id, examination_id, specimen_id, version, value, unit, status) "
        . "VALUES ('P-1', 'H-1', 'OI-1', 'EX-1', 'S-1', 1, '4.9', 'mmol/L', 'PENDING')",
    );

    $pdoRepository = new PdoLabResultDecisionRepository($pdo);
    $pdoResult = $pdoRepository->findById('P-1');
    $checks->same('4.9', $pdoResult?->value, 'Repository PDO recupera el resultado');
    $pdoResponse = week04Router(week04Workflow($pdoRepository))->dispatch(new ReviewHttpRequest(
        'POST',
        '/results/P-1/decision',
        ['expectedVersion' => 1, 'decision' => 'VALIDATE'],
        'BIO-1',
        'H-1',
    ));
    $checks->same(200, $pdoResponse->statusCode, 'MVC con Repository PDO confirma la operación');
    $checks->same(
        'VALIDATED',
        (string) $pdo->query("SELECT status FROM lab_result WHERE id = 'P-1'")->fetchColumn(),
        'PDO persiste la transición del resultado',
    );
    $checks->same(
        1,
        (int) $pdo->query("SELECT COUNT(*) FROM lab_result_decision WHERE result_id = 'P-1'")->fetchColumn(),
        'PDO persiste exactamente una decisión auditable',
    );
    $checks->same(
        '4.9',
        (string) $pdo->query("SELECT value FROM lab_result WHERE id = 'P-1'")->fetchColumn(),
        'PDO mantiene inmutable el valor revisado',
    );

    $pdo->exec(
        "INSERT INTO lab_result "
        . "(id, hospital_id, order_item_id, examination_id, specimen_id, version, value, unit, status) "
        . "VALUES ('P-2', 'H-1', 'OI-2', 'EX-1', 'S-2', 1, '7.1', 'mmol/L', 'PENDING')",
    );
    $pdo->exec('CREATE TRIGGER reject_audit BEFORE INSERT ON lab_result_decision BEGIN SELECT RAISE(FAIL, "audit failure"); END');
    $failedResponse = week04Router(week04Workflow($pdoRepository))->dispatch(new ReviewHttpRequest(
        'POST',
        '/results/P-2/decision',
        ['expectedVersion' => 1, 'decision' => 'VALIDATE'],
        'BIO-1',
        'H-1',
    ));
    $checks->same(503, $failedResponse->statusCode, 'falla PDO de auditoría se informa como 503');
    $checks->same(
        'PENDING',
        (string) $pdo->query("SELECT status FROM lab_result WHERE id = 'P-2'")->fetchColumn(),
        'la transacción revierte el estado si falla la auditoría',
    );
    $checks->same(
        0,
        (int) $pdo->query("SELECT COUNT(*) FROM lab_result_decision WHERE result_id = 'P-2'")->fetchColumn(),
        'la falla no deja una auditoría parcial',
    );

    $brokenPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $brokenRepository = new PdoLabResultDecisionRepository($brokenPdo);
    $readFailure = week04Router(week04Workflow($brokenRepository))->dispatch(new ReviewHttpRequest(
        'POST',
        '/results/MISSING/decision',
        ['expectedVersion' => 1, 'decision' => 'VALIDATE'],
        'BIO-1',
        'H-1',
    ));
    $checks->same(503, $readFailure->statusCode, 'falla PDO al leer se traduce a 503 sin filtrar detalles');
} else {
    echo "SKIP: pruebas PDO/SQLite; el driver sqlite no está instalado.\n";
}

exit($checks->finish());

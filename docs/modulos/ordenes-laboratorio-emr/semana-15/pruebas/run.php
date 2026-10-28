<?php

declare(strict_types=1);

require_once __DIR__ . '/TestDoubles.php';

$totals = ['UNIT' => 0, 'WHITEBOX' => 0, 'BLACKBOX' => 0];
$passed = 0;
$failed = 0;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$test = static function (string $type, string $id, string $title, Closure $body) use (
    &$totals,
    &$passed,
    &$failed,
): void {
    $totals[$type]++;
    try {
        $body();
        $passed++;
        echo "PASS [{$type} {$id}] {$title}\n";
    } catch (Throwable $exception) {
        $failed++;
        fwrite(STDERR, "FAIL [{$type} {$id}] {$title}: {$exception->getMessage()}\n");
    }
};

$test('UNIT', 'U-01', 'emisión crea la orden completa con sus referencias y partidas', static function () use ($assert): void {
    $double = new OrdersWorkflowDouble();
    $result = issueWorkflow($double)->execute(issueCommand(
        testCodes: ['TEST-CBC', 'TEST-GLUCOSE'],
    ));

    $assert($result->status === IssueStatus::CREATED, 'La emisión autorizada debe quedar creada.');
    $assert($result->order?->patientId === 'PATIENT-1', 'Se conserva el paciente.');
    $assert($result->order?->encounterId === 'ENCOUNTER-1', 'Se conserva el episodio.');
    $assert($result->order?->requestedBy === 'CLINICIAN-1', 'Se conserva el solicitante.');
    $assert($result->order?->testCodes === ['TEST-CBC', 'TEST-GLUCOSE'], 'Se conservan todas las pruebas.');
    $assert($result->order?->status === 'TEST_INITIAL', 'Se usa el estado inicial de la política.');
    $assert($double->createCalls === 1, 'Se solicita una creación atómica.');
});

$test('UNIT', 'U-02', 'consulta devuelve exactamente el estado persistido del episodio', static function () use ($assert): void {
    $double = new OrdersWorkflowDouble();
    $savedOrder = $double->order(status: 'TEST_NEXT');
    $double->searchResult = new SearchResult(SearchStatus::FOUND, [$savedOrder]);

    $result = searchWorkflow($double)->execute('CLINICIAN-1', 'PATIENT-1', 'ENCOUNTER-1');

    $assert($result->status === SearchStatus::FOUND, 'La búsqueda existente debe responder FOUND.');
    $assert($result->orders === [$savedOrder], 'La consulta devuelve la orden recibida del repositorio.');
    $assert($result->orders[0]->status === 'TEST_NEXT', 'El seguimiento expone el estado persistido.');
    $assert($double->lastSearch === ['PATIENT-1', 'ENCOUNTER-1'], 'La búsqueda se limita al paciente y episodio.');
});

$test('UNIT', 'U-03', 'actualización válida usa el estado leído como precondición', static function () use ($assert): void {
    $double = new OrdersWorkflowDouble();
    $double->lookupResult = new OrderLookupResult(
        LookupState::FOUND,
        $double->order(status: 'TEST_NEXT'),
    );

    $result = updateWorkflow($double)->execute('TEST-LAB-SERVICE', 'ORDER-TEST-001', 'TEST_FINAL');

    $assert($result === UpdateStatus::UPDATED, 'La transición aceptada debe confirmarse.');
    $assert($double->lastUpdate === ['ORDER-TEST-001', 'TEST_NEXT', 'TEST_FINAL'], 'La escritura compara el estado esperado.');
    $assert($double->transitionCalls === 1, 'La política del ciclo de vida valida la transición.');
});

$test('WHITEBOX', 'WB-01', 'emisión recorre entradas, permiso, contexto, catálogo y persistencia en orden', static function () use ($assert): void {
    $double = new OrdersWorkflowDouble();
    $invalid = issueWorkflow($double)->execute(issueCommand(testCodes: []));
    $assert($invalid->status === IssueStatus::INVALID_REQUEST, 'Lista vacía se rechaza.');
    $assert($double->authorizationCalls === 0 && $double->createCalls === 0, 'Entrada inválida termina antes de dependencias.');

    $double = new OrdersWorkflowDouble();
    $double->authorizationDecision = Decision::DENIED;
    $denied = issueWorkflow($double)->execute(issueCommand());
    $assert($denied->status === IssueStatus::FORBIDDEN, 'Permiso denegado se representa explícitamente.');
    $assert($double->contextCalls === 0 && $double->catalogCalls === 0 && $double->createCalls === 0, 'No se consulta contexto, catálogo ni escritura tras denegación.');

    foreach ([
        [static function (OrdersWorkflowDouble $d): void { $d->contextDecision = ValidationDecision::INVALID; }, IssueStatus::INVALID_CONTEXT],
        [static function (OrdersWorkflowDouble $d): void { $d->contextDecision = ValidationDecision::UNAVAILABLE; }, IssueStatus::UNAVAILABLE],
        [static function (OrdersWorkflowDouble $d): void { $d->catalogDecision = ValidationDecision::INVALID; }, IssueStatus::INVALID_TESTS],
        [static function (OrdersWorkflowDouble $d): void { $d->catalogDecision = ValidationDecision::UNAVAILABLE; }, IssueStatus::UNAVAILABLE],
    ] as [$configure, $expected]) {
        $double = new OrdersWorkflowDouble();
        $configure($double);
        $result = issueWorkflow($double)->execute(issueCommand());
        $assert($result->status === $expected, "La rama de emisión debe producir {$expected->name}.");
        $assert($double->createCalls === 0, 'Las precondiciones impiden la persistencia.');
    }
});

$test('WHITEBOX', 'WB-02', 'actualización cubre permiso, lookup, política y resultados de escritura', static function () use ($assert): void {
    $double = new OrdersWorkflowDouble();
    $double->sourceTrusted = false;
    $result = updateWorkflow($double)->execute('UNTRUSTED', 'ORDER-TEST-001', 'TEST_NEXT');
    $assert($result === UpdateStatus::REJECTED, 'Origen no confiable se rechaza.');
    $assert($double->lookupCalls === 0, 'No se lee orden de origen no confiable.');

    foreach ([
        [new OrderLookupResult(LookupState::NOT_FOUND), true, UpdateStatus::NOT_FOUND, 0],
        [new OrderLookupResult(LookupState::UNAVAILABLE), true, UpdateStatus::UNAVAILABLE, 0],
        [
            new OrderLookupResult(
                LookupState::FOUND,
                new LabOrder('ORDER-TEST-001', 'PATIENT-1', 'ENCOUNTER-1', 'CLINICIAN-1', ['TEST-CBC'], 'TEST_INITIAL'),
            ),
            false,
            UpdateStatus::REJECTED,
            0,
        ],
        [new OrderLookupResult(LookupState::FOUND, null), true, UpdateStatus::UNAVAILABLE, 0],
    ] as [$lookup, $transitionAllowed, $expected, $writes]) {
        $double = new OrdersWorkflowDouble();
        $double->lookupResult = $lookup;
        $double->transitionAllowed = $transitionAllowed;
        $result = updateWorkflow($double)->execute('TEST-LAB-SERVICE', 'ORDER-TEST-001', 'TEST_NEXT');
        $assert($result === $expected, "La rama debe producir {$expected->name}.");
        $assert($double->updateCalls === $writes, 'No se escribe si el lookup/política no autoriza.');
    }

    foreach ([
        [WriteState::NOT_FOUND, UpdateStatus::NOT_FOUND],
        [WriteState::CONFLICT, UpdateStatus::CONFLICT],
        [WriteState::UNAVAILABLE, UpdateStatus::PERSISTENCE_FAILURE],
        [WriteState::SAVED, UpdateStatus::PERSISTENCE_FAILURE],
    ] as [$writeState, $expected]) {
        $double = new OrdersWorkflowDouble();
        $double->updateResult = $writeState;
        $result = updateWorkflow($double)->execute('TEST-LAB-SERVICE', 'ORDER-TEST-001', 'TEST_NEXT');
        $assert($result === $expected, "La salida {$writeState->name} se mapea correctamente.");
    }
});

$test('BLACKBOX', 'BB-01', 'clases válidas de una prueba o varias generan una orden equivalente', static function () use ($assert): void {
    foreach ([['TEST-CBC'], ['TEST-CBC', 'TEST-GLUCOSE']] as $codes) {
        $double = new OrdersWorkflowDouble();
        $result = issueWorkflow($double)->execute(issueCommand(testCodes: $codes));
        $assert($result->status === IssueStatus::CREATED, 'Selección válida produce orden creada.');
        $assert($result->order?->testCodes === $codes, 'Se conserva la selección sin omitir partidas.');
    }
});

$test('BLACKBOX', 'BB-02', 'valores vacíos y límite mínimo de selección distinguen solicitud inválida', static function () use ($assert): void {
    foreach ([
        issueCommand(actorId: ' '),
        issueCommand(patientId: ''),
        issueCommand(encounterId: ' '),
        issueCommand(testCodes: []),
        issueCommand(testCodes: [' ']),
    ] as $command) {
        $double = new OrdersWorkflowDouble();
        $result = issueWorkflow($double)->execute($command);
        $assert($result->status === IssueStatus::INVALID_REQUEST, 'Dato obligatorio ausente/blank se rechaza.');
        $assert($double->authorizationCalls === 0 && $double->createCalls === 0, 'No hay efectos antes de validar entrada.');
    }

    $double = new OrdersWorkflowDouble();
    $minimum = issueWorkflow($double)->execute(issueCommand(testCodes: ['TEST-CBC']));
    $assert($minimum->status === IssueStatus::CREATED, 'Una prueba válida satisface el mínimo de selección.');
});

$test('BLACKBOX', 'BB-03', 'permisos clínicos y origen de seguimiento bloquean acceso no autorizado', static function () use ($assert): void {
    $double = new OrdersWorkflowDouble();
    $double->authorizationDecision = Decision::DENIED;
    $issue = issueWorkflow($double)->execute(issueCommand());
    $assert($issue->status === IssueStatus::FORBIDDEN, 'Emisión no autorizada se deniega.');
    $assert($double->createCalls === 0, 'Emisión denegada no escribe.');

    $double = new OrdersWorkflowDouble();
    $double->authorizationDecision = Decision::DENIED;
    $search = searchWorkflow($double)->execute('UNAUTHORIZED', 'PATIENT-1', 'ENCOUNTER-1');
    $assert($search->status === SearchStatus::FORBIDDEN, 'Consulta no autorizada se deniega.');
    $assert($double->searchCalls === 0, 'Consulta denegada no llega al repositorio.');

    $double = new OrdersWorkflowDouble();
    $double->sourceTrusted = false;
    $update = updateWorkflow($double)->execute('UNTRUSTED', 'ORDER-TEST-001', 'TEST_NEXT');
    $assert($update === UpdateStatus::REJECTED, 'Actualización de origen no autorizado se rechaza.');
    $assert($double->lookupCalls === 0 && $double->updateCalls === 0, 'Origen no autorizado no lee ni modifica.');
});

$test('BLACKBOX', 'BB-04', 'resultados vacíos, fallas técnicas y conflictos no se confunden', static function () use ($assert): void {
    $double = new OrdersWorkflowDouble();
    $double->searchResult = new SearchResult(SearchStatus::EMPTY);
    $empty = searchWorkflow($double)->execute('CLINICIAN-1', 'PATIENT-1', 'ENCOUNTER-1');
    $assert($empty->status === SearchStatus::EMPTY && $empty->orders === [], 'Sin órdenes es resultado vacío válido.');

    $double = new OrdersWorkflowDouble();
    $double->searchResult = new SearchResult(SearchStatus::UNAVAILABLE);
    $unavailable = searchWorkflow($double)->execute('CLINICIAN-1', 'PATIENT-1', 'ENCOUNTER-1');
    $assert($unavailable->status === SearchStatus::UNAVAILABLE, 'Falla de consulta no se convierte en vacío.');
    $assert($unavailable->orders === [], 'La consulta fallida no inventa estado.');

    $double = new OrdersWorkflowDouble();
    $double->createResult = new WriteResult(WriteState::UNAVAILABLE);
    $writeFailure = issueWorkflow($double)->execute(issueCommand());
    $assert($writeFailure->status === IssueStatus::PERSISTENCE_FAILURE, 'Falla de emisión no confirma una orden.');
    $assert($writeFailure->order === null, 'Falla de emisión no devuelve orden de éxito.');

    $double = new OrdersWorkflowDouble();
    $double->updateResult = WriteState::CONFLICT;
    $conflict = updateWorkflow($double)->execute('TEST-LAB-SERVICE', 'ORDER-TEST-001', 'TEST_NEXT');
    $assert($conflict === UpdateStatus::CONFLICT, 'Conflicto concurrente se mantiene distinguible.');

    $double = new OrdersWorkflowDouble();
    $double->lookupResult = new OrderLookupResult(LookupState::NOT_FOUND);
    $missing = updateWorkflow($double)->execute('TEST-LAB-SERVICE', 'ORDER-MISSING', 'TEST_NEXT');
    $assert($missing === UpdateStatus::NOT_FOUND, 'Orden inexistente no se confunde con falla técnica.');
});

echo "\n";
foreach ($totals as $type => $count) {
    echo "{$type}: {$count} pruebas\n";
}
echo sprintf("RESULTADO: %d/9 pruebas aprobadas; %d fallidas.\n", $passed, $failed);

if ($failed > 0) {
    exit(1);
}

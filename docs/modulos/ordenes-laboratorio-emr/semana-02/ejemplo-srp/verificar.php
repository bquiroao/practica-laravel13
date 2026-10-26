<?php

declare(strict_types=1);

require_once __DIR__ . '/src/LabOrderWorkflow.php';
require_once __DIR__ . '/src/InMemoryAdapters.php';

function verifySame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            "{$message}: esperado " . var_export($expected, true)
            . ', recibido ' . var_export($actual, true),
        );
    }

    echo "PASS: {$message}\n";
}

$assertions = 0;
$check = static function (mixed $expected, mixed $actual, string $message) use (&$assertions): void {
    verifySame($expected, $actual, $message);
    $assertions++;
};

$fixture = new InMemoryLabOrderFixture();
$fixture->authorizeContext('CLINICIAN-1', 'PATIENT-1', 'ENCOUNTER-1');
$fixture->authorizeContext('CLINICIAN-1', 'PATIENT-2', 'ENCOUNTER-2');
$fixture->allowContext('PATIENT-1', 'ENCOUNTER-1');
$fixture->allowTest('TEST-CBC');
$fixture->allowTest('TEST-GLUCOSE');
$fixture->trustSource('TEST-LAB-SERVICE');

$issue = new IssueLabOrder($fixture, $fixture, $fixture, $fixture, $fixture);
$command = new IssueOrderCommand(
    'CLINICIAN-1',
    'PATIENT-1',
    'ENCOUNTER-1',
    ['TEST-CBC', 'TEST-GLUCOSE'],
);
$created = $issue->execute($command);
$check(IssueStatus::CREATED, $created->status, 'CA-01 emite orden con pruebas disponibles');
$check('PATIENT-1', $created->order?->patientId, 'CA-01 conserva la referencia del paciente');
$check('ENCOUNTER-1', $created->order?->encounterId, 'CA-01 conserva la referencia del episodio');
$check('CLINICIAN-1', $created->order?->requestedBy, 'CA-01 conserva la referencia del solicitante');
$check(['TEST-CBC', 'TEST-GLUCOSE'], $created->order?->testCodes, 'CA-01 persiste todas las partidas');
$check('TEST_INITIAL', $created->order?->status, 'CA-01 usa estado sintético provisto por la política');

$invalid = $issue->execute(new IssueOrderCommand(
    'CLINICIAN-1',
    'PATIENT-1',
    'ENCOUNTER-1',
    [],
));
$check(IssueStatus::INVALID_REQUEST, $invalid->status, 'CA-02 rechaza solicitud sin pruebas');
$invalid = $issue->execute(new IssueOrderCommand(
    'CLINICIAN-1',
    'PATIENT-1',
    'ENCOUNTER-1',
    [''],
));
$check(IssueStatus::INVALID_REQUEST, $invalid->status, 'CA-02 rechaza código de prueba vacío');

$denied = $issue->execute(new IssueOrderCommand(
    'CLINICIAN-UNAUTHORIZED',
    'PATIENT-1',
    'ENCOUNTER-1',
    ['TEST-CBC'],
));
$check(IssueStatus::FORBIDDEN, $denied->status, 'CA-03 deniega emisión no autorizada');
$check(1, $fixture->orderCount(), 'CA-03 no escribe al denegar');

$invalidContext = $issue->execute(new IssueOrderCommand(
    'CLINICIAN-1',
    'PATIENT-2',
    'ENCOUNTER-2',
    ['TEST-CBC'],
));
$check(IssueStatus::INVALID_CONTEXT, $invalidContext->status, 'CA-04 rechaza contexto EMR inconsistente');
$check(1, $fixture->orderCount(), 'CA-04 no persiste contexto inválido');

$invalidTests = $issue->execute(new IssueOrderCommand(
    'CLINICIAN-1',
    'PATIENT-1',
    'ENCOUNTER-1',
    ['TEST-UNKNOWN'],
));
$check(IssueStatus::INVALID_TESTS, $invalidTests->status, 'CA-05 rechaza prueba no disponible');

$search = new FindLabOrders($fixture, $fixture);
$found = $search->execute('CLINICIAN-1', 'PATIENT-1', 'ENCOUNTER-1');
$check(SearchStatus::FOUND, $found->status, 'CA-08 encuentra órdenes del episodio autorizado');
$check(1, count($found->orders), 'CA-08 devuelve la orden persistida');
$empty = $search->execute('CLINICIAN-1', 'PATIENT-2', 'ENCOUNTER-2');
$check(SearchStatus::EMPTY, $empty->status, 'CA-09 distingue episodio sin órdenes');
$forbidden = $search->execute('CLINICIAN-UNAUTHORIZED', 'PATIENT-1', 'ENCOUNTER-1');
$check(SearchStatus::FORBIDDEN, $forbidden->status, 'CA-06 deniega consulta no autorizada');

$fixture->failNextWrite();
$writeFailure = $issue->execute(new IssueOrderCommand(
    'CLINICIAN-1',
    'PATIENT-1',
    'ENCOUNTER-1',
    ['TEST-CBC'],
));
$check(IssueStatus::PERSISTENCE_FAILURE, $writeFailure->status, 'CA-07 comunica falla al persistir');
$check(null, $writeFailure->order, 'CA-07 no confirma orden tras falla de persistencia');
$check(1, $fixture->orderCount(), 'CA-07 no deja una escritura parcial');

$fixture->failNextSearch();
$readFailure = $search->execute('CLINICIAN-1', 'PATIENT-1', 'ENCOUNTER-1');
$check(SearchStatus::UNAVAILABLE, $readFailure->status, 'CA-10 reporta falla de seguimiento');
$check([], $readFailure->orders, 'CA-10 no sustituye falla con estado inventado');

$applyStatus = new ApplyLabOrderStatus($fixture, $fixture, $fixture);
$orderId = $created->order?->id ?? '';
$rejectedSource = $applyStatus->execute('UNTRUSTED-SOURCE', $orderId, 'TEST_NEXT');
$check(UpdateStatus::REJECTED, $rejectedSource, 'CA-12 rechaza origen no autorizado');
$rejectedTransition = $applyStatus->execute('TEST-LAB-SERVICE', $orderId, 'UNAPPROVED_STATUS');
$check(UpdateStatus::REJECTED, $rejectedTransition, 'CA-12 rechaza transición no acordada por la política');
$updated = $applyStatus->execute('TEST-LAB-SERVICE', $orderId, 'TEST_NEXT');
$check(UpdateStatus::UPDATED, $updated, 'CA-11 persiste transición aceptada por la política');
$updatedSearch = $search->execute('CLINICIAN-1', 'PATIENT-1', 'ENCOUNTER-1');
$check('TEST_NEXT', $updatedSearch->orders[0]->status, 'CA-11 consulta el último estado persistido');

$fixture->failNextUpdate();
$updateFailure = $applyStatus->execute('TEST-LAB-SERVICE', $orderId, 'TEST_FINAL');
$check(UpdateStatus::PERSISTENCE_FAILURE, $updateFailure, 'CA-13 informa falla al persistir actualización');
$check('TEST_NEXT', $search->execute('CLINICIAN-1', 'PATIENT-1', 'ENCOUNTER-1')->orders[0]->status, 'CA-13 conserva estado tras falla de escritura');

$dependencies = static fn (string $class): array => array_map(
    static fn (ReflectionParameter $parameter): ?string => $parameter->getType()?->getName(),
    (new ReflectionMethod($class, '__construct'))->getParameters(),
);
$issueDependencies = $dependencies(IssueLabOrder::class);
$searchDependencies = $dependencies(FindLabOrders::class);
$updateDependencies = $dependencies(ApplyLabOrderStatus::class);
$check(
    [ClinicalAuthorization::class, ClinicalContextValidator::class, LabCatalog::class, OrderLifecyclePolicy::class, LabOrderRepository::class],
    $issueDependencies,
    'CA-14 emisión depende de contratos cohesionados',
);
$check(
    [ClinicalAuthorization::class, LabOrderRepository::class],
    $searchDependencies,
    'CA-14 consulta mantiene dependencias propias',
);
$check(
    [StatusUpdateSourceAuthorization::class, OrderLifecyclePolicy::class, LabOrderRepository::class],
    $updateDependencies,
    'CA-14 actualización tiene contratos propios',
);
$allDependencies = array_merge($issueDependencies, $searchDependencies, $updateDependencies);
$check(
    true,
    array_reduce(
        $allDependencies,
        static fn (bool $allInterfaces, string $type): bool => $allInterfaces && interface_exists($type),
        true,
    ),
    'CA-15 los casos de uso dependen de interfaces y no de adaptadores concretos',
);
$check(
    false,
    in_array(OrderLifecyclePolicy::class, $searchDependencies, true),
    'CA-16 consulta de seguimiento no depende del ciclo de vida de escritura',
);
$check(
    true,
    in_array(OrderLifecyclePolicy::class, $updateDependencies, true),
    'CA-16 el caso de actualización recibe la política de estados',
);

echo "OK: {$assertions} aserciones.\n";

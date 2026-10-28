<?php

declare(strict_types=1);

require_once __DIR__ . '/UniqueTestCatalog.php';
require_once __DIR__ . '/../../semana-02/ejemplo-srp/src/InMemoryAdapters.php';
$n = 0;
function check(mixed $expected, mixed $actual, string $message): void {
    global $n;
    if ($expected !== $actual) { throw new RuntimeException($message); }
    ++$n;
    echo "PASS: $message\n";
}
$f = new InMemoryLabOrderFixture();
$f->authorizeContext('MED-DEMO', 'PAC-DEMO', 'EPI-DEMO');
$f->allowContext('PAC-DEMO', 'EPI-DEMO');
$f->allowTest('TEST-A');
$f->allowTest('TEST-B');
$cmd = fn(array $codes, string $actor='MED-DEMO') => new IssueOrderCommand($actor, 'PAC-DEMO', 'EPI-DEMO', $codes);
$legacy = new IssueLabOrder($f, $f, $f, $f, $f);
check(IssueStatus::CREATED, $legacy->execute($cmd(['TEST-A', 'TEST-A']))->status, 'antes: ejemplo acepta codigo repetido');
$baseline = $f->orderCount();
$changed = new IssueLabOrder($f, $f, new UniqueTestCatalog($f), $f, $f);
check(IssueStatus::INVALID_TESTS, $changed->execute($cmd(['TEST-A','TEST-A']))->status, 'despues: repeticion rechazada');
check($baseline, $f->orderCount(), 'rechazo no escribe orden parcial');
$ok=$changed->execute($cmd(['TEST-A','TEST-B']));
check(IssueStatus::CREATED, $ok->status, 'pruebas diferentes conservan camino feliz');
check(['TEST-A','TEST-B'], $ok->order?->testCodes, 'conserva partidas y orden solicitado');
check('PAC-DEMO', $ok->order?->patientId, 'conserva referencia de expediente');
check('EPI-DEMO', $ok->order?->encounterId, 'conserva episodio');
check(IssueStatus::INVALID_TESTS, $changed->execute($cmd(['TEST-UNKNOWN']))->status, 'catalogo sigue validando disponibilidad');
check(IssueStatus::FORBIDDEN, $changed->execute($cmd(['TEST-A'], 'OTRO'))->status, 'autorizacion sigue antes de emitir');
$f->failNextWrite();
check(IssueStatus::PERSISTENCE_FAILURE, $changed->execute($cmd(['TEST-A']))->status, 'no confirma persistencia fallida');
$find=new FindLabOrders($f,$f);
check(SearchStatus::FOUND, $find->execute('MED-DEMO','PAC-DEMO','EPI-DEMO')->status, 'seguimiento conserva consulta autorizada');
$f->failNextSearch();
check(SearchStatus::UNAVAILABLE, $find->execute('MED-DEMO','PAC-DEMO','EPI-DEMO')->status, 'falla de lectura no es estado actual');
echo "OK: $n aserciones del cambio.\n";
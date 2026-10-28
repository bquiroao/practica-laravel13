<?php

declare(strict_types=1);

require_once __DIR__ . '/../../semana-02/ejemplo-dip/despues/ReviewWorkflow.php';
require_once __DIR__ . '/../../semana-02/ejemplo-dip/despues/InMemoryLabResultDecisionRepository.php';

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

final class FixedAuth implements ReviewerAuthorization
{
    /** @param list<string> $allowed */
    public function __construct(private array $allowed)
    {
    }

    public function canReview(string $actorId, string $hospitalId): bool
    {
        return in_array($actorId . '@' . $hospitalId, $this->allowed, true);
    }
}

final class FixedClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-27T12:30:00-06:00');
    }
}

/** @return array{repo:InMemoryLabResultDecisionRepository,uc:ReviewPendingLabResult} */
function world(): array
{
    $repo = new InMemoryLabResultDecisionRepository();
    $repo->seed(new LabResult('R1', 'H1', 'OI1', 'EX1', 'SP1', 1, '5.1', 'mmol/L', LabResultStatus::PENDING));
    $repo->seed(new LabResult('R2', 'H1', 'OI2', 'EX2', 'SP2', 1, '6.0', 'mmol/L', LabResultStatus::PENDING));
    $repo->seed(new LabResult('R3', 'H2', 'OI3', 'EX3', 'SP3', 1, '4.1', 'mmol/L', LabResultStatus::PENDING));

    return [
        'repo' => $repo,
        'uc' => new ReviewPendingLabResult(new FixedAuth(['bioq-7@H1']), new FixedClock(), $repo),
    ];
}

/** @return array{ok:bool, outcome?:ReviewOutcome, traceId?:string, signatureHash?:string, reason?:string} */
function decideWithPracticalChange(
    ReviewPendingLabResult $uc,
    array $session,
    array $payload,
): array {
    // Cambio práctico defendido: decisión firmada y trazable (firma + traceId), actor/hospital solo de sesión.
    $signature = trim((string) ($payload['signature'] ?? ''));
    if ($signature === '') {
        return ['ok' => false, 'reason' => 'MISSING_SIGNATURE'];
    }

    $decision = ReviewDecision::tryFrom((string) ($payload['decision'] ?? ''));
    if ($decision === null) {
        return ['ok' => false, 'reason' => 'INVALID_DECISION'];
    }

    $command = new ReviewCommand(
        resultId: (string) ($payload['resultId'] ?? ''),
        expectedVersion: (int) ($payload['expectedVersion'] ?? 0),
        actorId: (string) $session['actorId'],
        hospitalId: (string) $session['hospitalId'],
        decision: $decision,
        reason: (string) ($payload['reason'] ?? ''),
    );

    $outcome = $uc->execute($command);
    $traceId = 'TRC-' . substr(hash('sha256', json_encode([$command->resultId, $command->actorId, $command->hospitalId, $decision->value, $signature])), 0, 12);

    return [
        'ok' => $outcome->status === ReviewOutcomeStatus::VALIDATED || $outcome->status === ReviewOutcomeStatus::REJECTED,
        'outcome' => $outcome,
        'traceId' => $traceId,
        'signatureHash' => hash('sha256', $signature),
    ];
}

step('A. Límite del diseño actual');
['repo' => $repoA, 'uc' => $ucA] = world();
$raw = $ucA->execute(new ReviewCommand('R1', 1, 'bioq-7', 'H1', ReviewDecision::VALIDATE));
check('el diseño actual valida sin campo de firma (límite real)', $raw->status === ReviewOutcomeStatus::VALIDATED);
check('la decisión queda registrada sin huella de firma', count($repoA->decisions()) === 1 && $repoA->decisions()[0]->actorId === 'bioq-7');

step('B. Cambio práctico defendido: firma + trazabilidad');
['repo' => $repoB, 'uc' => $ucB] = world();
$missingSignature = decideWithPracticalChange($ucB, ['actorId' => 'bioq-7', 'hospitalId' => 'H1'], [
    'resultId' => 'R1',
    'expectedVersion' => 1,
    'decision' => 'VALIDATE',
]);
check('sin firma, la política propuesta rechaza la decisión', $missingSignature['ok'] === false && $missingSignature['reason'] === 'MISSING_SIGNATURE');
check('sin firma no se escribe ninguna decisión', count($repoB->decisions()) === 0);

$okValidate = decideWithPracticalChange($ucB, ['actorId' => 'bioq-7', 'hospitalId' => 'H1'], [
    'resultId' => 'R1',
    'expectedVersion' => 1,
    'decision' => 'VALIDATE',
    'signature' => 'BIOQ7#PIN2026',
    'actorId' => 'suplantacion',
    'hospitalId' => 'H9',
]);
check('con firma válida se acepta VALIDATE', $okValidate['ok'] === true
    && $okValidate['outcome']?->status === ReviewOutcomeStatus::VALIDATED);
check('actor/hospital se toman de sesión, no del payload', $repoB->decisions()[0]->actorId === 'bioq-7'
    && $repoB->decisions()[0]->hospitalId === 'H1');
check('se genera trazabilidad con traceId y hash de firma', str_starts_with((string) $okValidate['traceId'], 'TRC-')
    && strlen((string) $okValidate['signatureHash']) === 64);

step('C. Revisión bioquímica: rechazo, versión y estado');
['repo' => $repoC, 'uc' => $ucC] = world();
$rejectNoReason = decideWithPracticalChange($ucC, ['actorId' => 'bioq-7', 'hospitalId' => 'H1'], [
    'resultId' => 'R2',
    'expectedVersion' => 1,
    'decision' => 'REJECT',
    'signature' => 'BIOQ7#PIN2026',
]);
check('rechazo sin motivo sigue siendo inválido', $rejectNoReason['ok'] === false
    && $rejectNoReason['outcome']?->status === ReviewOutcomeStatus::INVALID_INPUT);

$rejectOk = decideWithPracticalChange($ucC, ['actorId' => 'bioq-7', 'hospitalId' => 'H1'], [
    'resultId' => 'R2',
    'expectedVersion' => 1,
    'decision' => 'REJECT',
    'reason' => 'Delta crítica no consistente',
    'signature' => 'BIOQ7#PIN2026',
]);
check('rechazo con motivo y firma se acepta', $rejectOk['ok'] === true
    && $rejectOk['outcome']?->status === ReviewOutcomeStatus::REJECTED);

$stale = decideWithPracticalChange($ucC, ['actorId' => 'bioq-7', 'hospitalId' => 'H1'], [
    'resultId' => 'R2',
    'expectedVersion' => 1,
    'decision' => 'VALIDATE',
    'signature' => 'BIOQ7#PIN2026',
]);
check('tras decidir, un reintento sobre el mismo estado responde INVALID_STATE', $stale['ok'] === false
    && $stale['outcome']?->status === ReviewOutcomeStatus::INVALID_STATE);

$otherHospital = decideWithPracticalChange($ucC, ['actorId' => 'bioq-7', 'hospitalId' => 'H1'], [
    'resultId' => 'R3',
    'expectedVersion' => 1,
    'decision' => 'VALIDATE',
    'signature' => 'BIOQ7#PIN2026',
]);
check('aislamiento por hospital se mantiene (NOT_FOUND en otro hospital)', $otherHospital['ok'] === false
    && $otherHospital['outcome']?->status === ReviewOutcomeStatus::NOT_FOUND);

echo PHP_EOL . ($passed + $failed) . " comprobaciones, $failed fallidas." . PHP_EOL;
exit($failed === 0 ? 0 : 1);


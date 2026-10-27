<?php

declare(strict_types=1);

// Adaptador HTTP SIMULADO sobre el caso de uso real de la Semana 2. No hay servidor: se prueba la lógica del contrato.
$dir = __DIR__ . '/../../semana-02/ejemplo-dip/despues';
require_once $dir . '/ReviewWorkflow.php';
require_once $dir . '/InMemoryLabResultDecisionRepository.php';

$passed = 0;
$failed = 0;
function check(string $name, bool $ok): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    $ok ? $passed++ : $failed++;
}
function step(string $t): void
{
    echo PHP_EOL . $t . PHP_EOL;
}

final class Session
{
    public function __construct(public string $actorId, public string $hospitalId) {}
}

final class Auth implements ReviewerAuthorization
{
    /** @param list<string> $reviewers */
    public function __construct(private array $reviewers) {}

    public function canReview(string $actorId, string $hospitalId): bool
    {
        return in_array($actorId, $this->reviewers, true);
    }
}

final class Clk implements Clock
{
    public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-10-27T10:00:00-06:00'); }
}

/** POST /api/v1/results/{id}/decisions con If-Match (versión) e Idempotency-Key. */
final class ProposedApi
{
    /** @var array<string, array{fingerprint:string, response:array}> */
    private array $idem = [];

    public function __construct(private ReviewPendingLabResult $uc) {}

    /** @return array{status:int, type?:string, body?:array} */
    public function decide(?Session $s, string $resultId, ?string $idemKey, ?string $ifMatch, array $body): array
    {
        if ($s === null) {
            return ['status' => 401, 'type' => 'unauthenticated'];
        }
        if ($idemKey === null || $idemKey === '') {
            return ['status' => 400, 'type' => 'missing-idempotency-key'];
        }
        if ($ifMatch === null || !ctype_digit($ifMatch)) {
            return ['status' => 428, 'type' => 'precondition-required'];
        }
        $decision = ReviewDecision::tryFrom((string) ($body['decision'] ?? ''));
        if ($decision === null) {
            return ['status' => 422, 'type' => 'invalid-decision'];
        }
        $fp = hash('sha256', json_encode([$resultId, $ifMatch, $body]));
        $k = $s->hospitalId . '|' . $idemKey;
        if (isset($this->idem[$k])) {
            return $this->idem[$k]['fingerprint'] === $fp
                ? $this->idem[$k]['response']
                : ['status' => 409, 'type' => 'idempotency-key-reuse'];
        }
        // El actor y el hospital salen de la sesión, nunca del cuerpo.
        $o = $this->uc->execute(new ReviewCommand($resultId, (int) $ifMatch, $s->actorId, $s->hospitalId, $decision, (string) ($body['reason'] ?? '')));
        $resp = match ($o->status) {
            ReviewOutcomeStatus::VALIDATED, ReviewOutcomeStatus::REJECTED => ['status' => 201, 'body' => ['decision' => $o->status->value]],
            ReviewOutcomeStatus::DENIED => ['status' => 403, 'type' => 'forbidden'],
            ReviewOutcomeStatus::INVALID_INPUT => ['status' => 422, 'type' => 'invalid-input'],
            ReviewOutcomeStatus::INVALID_CONTEXT => ['status' => 422, 'type' => 'incomplete-context'],
            ReviewOutcomeStatus::NOT_FOUND => ['status' => 404, 'type' => 'not-found'],
            ReviewOutcomeStatus::CONFLICT => ['status' => 409, 'type' => 'version-conflict'],
            ReviewOutcomeStatus::INVALID_STATE => ['status' => 409, 'type' => 'already-decided'],
            ReviewOutcomeStatus::PERSISTENCE_FAILURE => ['status' => 503, 'type' => 'unavailable'],
        };
        if ($resp['status'] < 500) {
            $this->idem[$k] = ['fingerprint' => $fp, 'response' => $resp];
        }
        return $resp;
    }
}

function world(): array
{
    $repo = new InMemoryLabResultDecisionRepository();
    $repo->seed(new LabResult('R1', 'H1', 'OI1', 'EX1', 'SP1', 1, '5.1', 'mmol/L', LabResultStatus::PENDING));
    $repo->seed(new LabResult('R2', 'H1', 'OI2', 'EX1', 'SP2', 1, '9.9', 'mmol/L', LabResultStatus::PENDING));
    $repo->seed(new LabResult('R3', 'H2', 'OI3', 'EX1', 'SP3', 1, '4.0', 'mmol/L', LabResultStatus::PENDING));
    $repo->seed(new LabResult('R4', 'H1', '', 'EX1', 'SP4', 1, '4.0', 'mmol/L', LabResultStatus::PENDING));
    $uc = new ReviewPendingLabResult(new Auth(['bioq-7']), new Clk(), $repo);
    return [new ProposedApi($uc), $repo, $uc];
}

$bio = new Session('bioq-7', 'H1');

step('Paso 1. Validar con versión vigente');
[$api, $repo] = world();
check('POST de validación responde 201', $api->decide($bio, 'R1', 'k1', '1', ['decision' => 'VALIDATE'])['status'] === 201);
check('el resultado quedó VALIDATED y hay 1 decisión', $repo->findById('R1')->status === LabResultStatus::VALIDATED && count($repo->decisions()) === 1);
check('la decisión guarda el actor de la sesión', $repo->decisions()[0]->actorId === 'bioq-7');

step('Paso 2. Idempotencia');
$again = $api->decide($bio, 'R1', 'k1', '1', ['decision' => 'VALIDATE']);
check('misma clave y contenido repite la respuesta original (201)', $again['status'] === 201);
check('sigue habiendo una sola decisión', count($repo->decisions()) === 1);
check('misma clave con otro contenido responde 409', $api->decide($bio, 'R1', 'k1', '1', ['decision' => 'REJECT', 'reason' => 'x'])['status'] === 409);

step('Paso 3. Límite: sin idempotencia, un reintento ya decidido se ve como conflicto');
$retry = $api->decide($bio, 'R1', 'k-nueva', '1', ['decision' => 'VALIDATE']);
check('LIMITE DEMOSTRADO: con otra clave el reintento da 409 already-decided', $retry['status'] === 409 && $retry['type'] === 'already-decided');

step('Paso 4. Rechazo exige motivo');
check('rechazar sin motivo responde 422', $api->decide($bio, 'R2', 'k2', '1', ['decision' => 'REJECT'])['status'] === 422);
check('rechazar con motivo responde 201', $api->decide($bio, 'R2', 'k3', '1', ['decision' => 'REJECT', 'reason' => 'Muestra hemolizada'])['status'] === 201);

step('Paso 5. Versión obsoleta');
[$api, $repo] = world();
check('If-Match distinto de la versión vigente responde 409 version-conflict', $api->decide($bio, 'R1', 'k1', '7', ['decision' => 'VALIDATE'])['type'] === 'version-conflict');

step('Paso 6. Entradas y precondiciones');
check('sin sesión responde 401', $api->decide(null, 'R1', 'k1', '1', ['decision' => 'VALIDATE'])['status'] === 401);
check('sin Idempotency-Key responde 400', $api->decide($bio, 'R1', null, '1', ['decision' => 'VALIDATE'])['status'] === 400);
check('sin If-Match responde 428', $api->decide($bio, 'R1', 'k1', null, ['decision' => 'VALIDATE'])['status'] === 428);
check('decisión desconocida responde 422', $api->decide($bio, 'R1', 'k1', '1', ['decision' => 'MAYBE'])['status'] === 422);

step('Paso 7. Autorización y aislamiento por hospital');
check('un usuario sin rol de revisor responde 403', $api->decide(new Session('tec-1', 'H1'), 'R1', 'k9', '1', ['decision' => 'VALIDATE'])['status'] === 403);
check('un resultado de otro hospital responde 404 (no se revela)', $api->decide($bio, 'R3', 'k10', '1', ['decision' => 'VALIDATE'])['status'] === 404);
check('un resultado sin ítem de orden responde 422 incomplete-context', $api->decide($bio, 'R4', 'k11', '1', ['decision' => 'VALIDATE'])['type'] === 'incomplete-context');

step('Paso 8. Falla de persistencia');
[$api, $repo] = world();
$repo->failNextWrite();
check('una falla al guardar responde 503', $api->decide($bio, 'R1', 'k12', '1', ['decision' => 'VALIDATE'])['status'] === 503);
check('el 503 no se guarda como respuesta idempotente: el reintento funciona (201)', $api->decide($bio, 'R1', 'k12', '1', ['decision' => 'VALIDATE'])['status'] === 201);

step('Paso 9. Límite: la versión no cambia al decidir');
check('LIMITE DEMOSTRADO: tras validar, la versión sigue siendo 1; el 409 lo da el estado, no la versión', $repo->findById('R1')->version === 1);

echo PHP_EOL . ($passed + $failed) . " comprobaciones, $failed fallidas." . PHP_EOL;
exit($failed === 0 ? 0 : 1);

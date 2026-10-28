<?php

declare(strict_types=1);

require_once __DIR__ . '/despues/ReviewWorkflow.php';
require_once __DIR__ . '/despues/InMemoryLabResultDecisionRepository.php';

final class FixedReviewerAuthorization implements ReviewerAuthorization
{
    public function __construct(private bool $allowed = true)
    {
    }

    public function canReview(string $actorId, string $hospitalId): bool
    {
        return $this->allowed;
    }
}

final class FixedClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-25T12:00:00-06:00');
    }
}

final class ConcurrentUpdateRepository implements LabResultDecisionRepository
{
    public function __construct(private InMemoryLabResultDecisionRepository $inner)
    {
    }

    public function findById(string $resultId): ?LabResult
    {
        $snapshot = $this->inner->findById($resultId);
        if ($snapshot !== null) {
            $this->inner->seed(new LabResult(
                resultId: $snapshot->resultId,
                hospitalId: $snapshot->hospitalId,
                orderItemId: $snapshot->orderItemId,
                examinationId: $snapshot->examinationId,
                specimenId: $snapshot->specimenId,
                version: $snapshot->version + 1,
                value: '5.5',
                unit: $snapshot->unit,
                status: LabResultStatus::PENDING,
            ));
        }

        return $snapshot;
    }

    public function decideAtomically(ReviewDecisionRecord $record): DecisionWriteStatus
    {
        return $this->inner->decideAtomically($record);
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            "{$message}: se esperaba " . var_export($expected, true)
            . ', se obtuvo ' . var_export($actual, true),
        );
    }

    echo "PASS: {$message}\n";
}

function makeResult(
    string $resultId = 'RESULT-1',
    int $version = 3,
    LabResultStatus $status = LabResultStatus::PENDING,
    string $hospitalId = 'HOSPITAL-1',
    bool $completeContext = true,
): LabResult {
    return new LabResult(
        resultId: $resultId,
        hospitalId: $hospitalId,
        orderItemId: $completeContext ? 'ORDER-ITEM-1' : '',
        examinationId: $completeContext ? 'EXAM-1' : '',
        specimenId: $completeContext ? 'SPECIMEN-1' : '',
        version: $version,
        value: '5.4',
        unit: 'mmol/L',
        status: $status,
    );
}

function makeCommand(
    ReviewDecision $decision = ReviewDecision::VALIDATE,
    int $version = 3,
    string $reason = '',
    string $hospitalId = 'HOSPITAL-1',
): ReviewCommand {
    return new ReviewCommand('RESULT-1', $version, 'BIOCHEMIST-7', $hospitalId, $decision, $reason);
}

function makeWorkflow(
    LabResultDecisionRepository $repository,
    bool $authorized = true,
): ReviewPendingLabResult {
    return new ReviewPendingLabResult(
        new FixedReviewerAuthorization($authorized),
        new FixedClock(),
        $repository,
    );
}

$assertions = 0;
$check = static function (mixed $expected, mixed $actual, string $message) use (&$assertions): void {
    assertSameValue($expected, $actual, $message);
    $assertions++;
};

$repository = new InMemoryLabResultDecisionRepository();
$repository->seed(makeResult());
$workflow = makeWorkflow($repository);
$outcome = $workflow->execute(makeCommand());
$check(ReviewOutcomeStatus::VALIDATED, $outcome->status, 'CA-01 valida una versión pendiente');
$check(LabResultStatus::VALIDATED, $repository->findById('RESULT-1')?->status, 'CA-01 cambia solo el estado');
$check('5.4', $repository->findById('RESULT-1')?->value, 'CA-01 conserva el valor revisado');
$check('BIOCHEMIST-7', $repository->decisions()[0]->actorId, 'CA-01 conserva la autoría');
$check(
    '2026-10-25T12:00:00-06:00',
    $repository->decisions()[0]->decidedAt->format(DATE_ATOM),
    'CA-01 registra el momento de la decisión',
);

$repository = new InMemoryLabResultDecisionRepository();
$repository->seed(makeResult());
$outcome = makeWorkflow($repository)->execute(
    makeCommand(ReviewDecision::REJECT, reason: 'Identificación de muestra por confirmar'),
);
$check(ReviewOutcomeStatus::REJECTED, $outcome->status, 'CA-02 rechaza con motivo');
$check(
    'Identificación de muestra por confirmar',
    $repository->decisions()[0]->reason,
    'CA-02 conserva el motivo del rechazo',
);
$check('5.4', $repository->findById('RESULT-1')?->value, 'CA-02 conserva el resultado rechazado');

$repository = new InMemoryLabResultDecisionRepository();
$repository->seed(makeResult());
$outcome = makeWorkflow($repository)->execute(makeCommand(ReviewDecision::REJECT));
$check(ReviewOutcomeStatus::INVALID_INPUT, $outcome->status, 'CA-03 exige motivo no vacío');
$check([], $repository->decisions(), 'CA-03 no persiste un rechazo sin motivo');
$check(LabResultStatus::PENDING, $repository->findById('RESULT-1')?->status, 'CA-03 mantiene pendiente el resultado');

$repository = new InMemoryLabResultDecisionRepository();
$repository->seed(makeResult());
$outcome = makeWorkflow($repository, authorized: false)->execute(makeCommand());
$check(ReviewOutcomeStatus::DENIED, $outcome->status, 'CA-04 deniega a quien no está autorizado');
$check([], $repository->decisions(), 'CA-04 no registra decisiones ante denegación');
$check(LabResultStatus::PENDING, $repository->findById('RESULT-1')?->status, 'CA-04 no modifica el resultado');

$repository = new InMemoryLabResultDecisionRepository();
$repository->seed(makeResult(completeContext: false));
$outcome = makeWorkflow($repository)->execute(makeCommand());
$check(ReviewOutcomeStatus::INVALID_CONTEXT, $outcome->status, 'CA-05 impide decidir sin contexto de orden, examen y muestra');
$check([], $repository->decisions(), 'CA-05 no persiste una decisión sin contexto');

$repository = new InMemoryLabResultDecisionRepository();
$outcome = makeWorkflow($repository)->execute(makeCommand());
$check(ReviewOutcomeStatus::NOT_FOUND, $outcome->status, 'CA-06 informa resultado inexistente sin decidir');

$repository = new InMemoryLabResultDecisionRepository();
$repository->seed(makeResult(status: LabResultStatus::VALIDATED));
$outcome = makeWorkflow($repository)->execute(makeCommand());
$check(ReviewOutcomeStatus::INVALID_STATE, $outcome->status, 'CA-07 no vuelve a decidir un resultado validado');
$check([], $repository->decisions(), 'CA-07 no crea una segunda decisión');

$repository = new InMemoryLabResultDecisionRepository();
$repository->seed(makeResult(version: 4));
$outcome = makeWorkflow($repository)->execute(makeCommand(version: 3));
$check(ReviewOutcomeStatus::CONFLICT, $outcome->status, 'CA-08 detecta una versión obsoleta');
$check([], $repository->decisions(), 'CA-08 no persiste decisiones obsoletas');
$check(LabResultStatus::PENDING, $repository->findById('RESULT-1')?->status, 'CA-08 conserva el estado vigente');

$repository = new InMemoryLabResultDecisionRepository();
$repository->seed(makeResult());
$racingRepository = new ConcurrentUpdateRepository($repository);
$outcome = makeWorkflow($racingRepository)->execute(makeCommand());
$check(ReviewOutcomeStatus::CONFLICT, $outcome->status, 'CA-09 detecta el cambio ocurrido durante la decisión');
$check([], $repository->decisions(), 'CA-09 el adaptador revalida antes de persistir');
$check('5.5', $repository->findById('RESULT-1')?->value, 'CA-09 conserva el valor actualizado concurrentemente');

$repository = new InMemoryLabResultDecisionRepository();
$repository->seed(makeResult());
$repository->failNextWrite();
$outcome = makeWorkflow($repository)->execute(makeCommand());
$check(ReviewOutcomeStatus::PERSISTENCE_FAILURE, $outcome->status, 'CA-10 expone la falla de persistencia');
$check([], $repository->decisions(), 'CA-10 no registra auditoría parcial');
$check(LabResultStatus::PENDING, $repository->findById('RESULT-1')?->status, 'CA-10 no deja un cambio parcial');

$repository = new InMemoryLabResultDecisionRepository();
$repository->seed(makeResult());
$outcome = makeWorkflow($repository)->execute(makeCommand(hospitalId: 'HOSPITAL-2'));
$check(ReviewOutcomeStatus::NOT_FOUND, $outcome->status, 'CA-11 no revela resultados de otro hospital');
$check([], $repository->decisions(), 'CA-11 no registra decisiones fuera del hospital');

$repository = new InMemoryLabResultDecisionRepository();
$repository->seed(makeResult());
$outcome = makeWorkflow($repository)->execute(makeCommand(version: 0));
$check(ReviewOutcomeStatus::INVALID_INPUT, $outcome->status, 'CA-12 rechaza una versión esperada inválida');
$check([], $repository->decisions(), 'CA-12 no persiste entradas inválidas');

$constructor = new ReflectionMethod(ReviewPendingLabResult::class, '__construct');
$dependencies = array_map(
    static fn (ReflectionParameter $parameter): ?string => $parameter->getType()?->getName(),
    $constructor->getParameters(),
);
$check(
    [
        ReviewerAuthorization::class,
        Clock::class,
        LabResultDecisionRepository::class,
    ],
    $dependencies,
    'CA-13 el caso de uso depende de abstracciones sustituibles',
);

echo "OK: {$assertions} aserciones.\n";

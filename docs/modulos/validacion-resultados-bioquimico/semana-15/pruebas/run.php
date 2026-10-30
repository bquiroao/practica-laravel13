<?php

declare(strict_types=1);

require_once __DIR__ . '/TestDoubles.php';

$total = 0;
$passed = 0;
$failed = 0;
$groupTotals = ['UNIT' => 0, 'WHITEBOX' => 0, 'BLACKBOX' => 0];

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$test = static function (string $group, string $id, string $name, Closure $body) use (
    &$total,
    &$passed,
    &$failed,
    &$groupTotals,
): void {
    $total++;
    $groupTotals[$group]++;

    try {
        $body();
        $passed++;
        echo "PASS [{$group} {$id}] {$name}\n";
    } catch (Throwable $exception) {
        $failed++;
        fwrite(STDERR, "FAIL [{$group} {$id}] {$name}: {$exception->getMessage()}\n");
    }
};

$test('UNIT', 'U-01', 'validar conserva el valor y la unidad revisados', static function () use ($assert): void {
    [$workflow, , $repository] = reviewFixture(specimenResult(value: '7.25', unit: 'mmol/L'));

    $outcome = $workflow->execute(reviewCommand());
    $result = $repository->inner->findById('RESULT-15');

    $assert($outcome->status === ReviewOutcomeStatus::VALIDATED, 'La decisión debe validar el resultado.');
    $assert($result?->value === '7.25', 'La validación no debe alterar el valor analítico.');
    $assert($result?->unit === 'mmol/L', 'La validación no debe alterar la unidad.');
});

$test('UNIT', 'U-02', 'la revisión consulta autorización con actor y hospital', static function () use ($assert): void {
    [$workflow, $authorization] = reviewFixture();

    $workflow->execute(reviewCommand());

    $assert($authorization->calls === 1, 'La autorización debe consultarse una vez.');
    $assert($authorization->lastActorId === 'BIOCHEMIST-15', 'La autorización debe identificar al bioquímico.');
    $assert($authorization->lastHospitalId === 'HOSPITAL-A', 'La autorización debe delimitar el hospital.');
});

$test('UNIT', 'U-03', 'el rechazo exige y conserva un motivo normalizado', static function () use ($assert): void {
    [$workflow, , $repository] = reviewFixture();

    $outcome = $workflow->execute(reviewCommand(
        decision: ReviewDecision::REJECT,
        reason: '  Revisar interferencia analítica  ',
    ));

    $assert($outcome->status === ReviewOutcomeStatus::REJECTED, 'El rechazo con motivo debe completarse.');
    $assert(
        $repository->inner->decisions()[0]->reason === 'Revisar interferencia analítica',
        'El motivo debe quedar normalizado y trazable.',
    );
});

$test('UNIT', 'U-04', 'la firma de auditoría identifica actor, hospital, versión y momento', static function () use ($assert): void {
    [$workflow, , $repository] = reviewFixture();

    $workflow->execute(reviewCommand());
    $record = $repository->inner->decisions()[0];

    $assert($record->actorId === 'BIOCHEMIST-15', 'El registro debe identificar a quien decide.');
    $assert($record->hospitalId === 'HOSPITAL-A', 'El registro debe conservar el ámbito hospitalario.');
    $assert($record->expectedVersion === 1, 'El registro debe conservar la versión revisada.');
    $assert($record->decidedAt->format(DATE_ATOM) === '2026-10-25T12:15:00-06:00', 'El registro debe conservar fecha y hora.');
    $assert($record->decision === ReviewDecision::VALIDATE, 'El registro debe conservar la decisión explícita.');
});

$test('UNIT', 'U-05', 'fallo de escritura no deja cambio de estado ni auditoría parcial', static function () use ($assert): void {
    $inner = new InMemoryLabResultDecisionRepository();
    $inner->seed(specimenResult());
    $inner->failNextWrite();
    $repository = new ObservedDecisionRepository($inner);
    $workflow = new ReviewPendingLabResult(
        new TestReviewerAuthorization(),
        new TestClock(),
        $repository,
    );

    $outcome = $workflow->execute(reviewCommand());

    $assert($outcome->status === ReviewOutcomeStatus::PERSISTENCE_FAILURE, 'El fallo debe ser explícito.');
    $assert($inner->findById('RESULT-15')?->status === LabResultStatus::PENDING, 'El resultado debe seguir pendiente.');
    $assert($inner->decisions() === [], 'No debe existir auditoría parcial.');
});

$test('UNIT', 'U-06', 'un valor del fixture fuera de referencia no se interpreta automáticamente', static function () use ($assert): void {
    [$workflow, , $repository] = reviewFixture(specimenResult(value: '999999', unit: 'mmol/L'));

    $outcome = $workflow->execute(reviewCommand());
    $result = $repository->inner->findById('RESULT-15');

    $assert($outcome->status === ReviewOutcomeStatus::VALIDATED, 'La decisión permanece bajo criterio del bioquímico.');
    $assert($result?->value === '999999', 'El flujo no aplica umbrales ni modifica el valor.');
});

$test('WHITEBOX', 'WB-01', 'cubre ramas de precondiciones, acceso, contexto y estado', static function () use ($assert): void {
    [$workflow, $authorization, $repository] = reviewFixture();
    $invalid = $workflow->execute(reviewCommand(resultId: ' '));
    $assert($invalid->status === ReviewOutcomeStatus::INVALID_INPUT, 'ID vacío activa la rama de entrada inválida.');
    $assert($authorization->calls === 0 && $repository->findCalls === 0, 'La validación de entrada corta el flujo.');

    [$workflow, $authorization, $repository] = reviewFixture(authorized: false);
    $denied = $workflow->execute(reviewCommand());
    $assert($denied->status === ReviewOutcomeStatus::DENIED, 'La rama de denegación debe ejecutarse.');
    $assert($repository->findCalls === 0, 'La denegación no debe consultar el resultado.');

    [$workflow] = reviewFixture(seed: false);
    $missing = $workflow->execute(reviewCommand());
    $assert($missing->status === ReviewOutcomeStatus::NOT_FOUND, 'Resultado ausente activa la rama no encontrado.');

    [$workflow] = reviewFixture(result: specimenResult(orderItemId: ''));
    $context = $workflow->execute(reviewCommand());
    $assert($context->status === ReviewOutcomeStatus::INVALID_CONTEXT, 'Asociación incompleta activa contexto inválido.');

    [$workflow] = reviewFixture(result: specimenResult(version: 2));
    $stale = $workflow->execute(reviewCommand(version: 1));
    $assert($stale->status === ReviewOutcomeStatus::CONFLICT, 'Versión antigua activa conflicto.');

    [$workflow] = reviewFixture(result: specimenResult(status: LabResultStatus::VALIDATED));
    $alreadyDecided = $workflow->execute(reviewCommand());
    $assert($alreadyDecided->status === ReviewOutcomeStatus::INVALID_STATE, 'Estado no pendiente impide una segunda decisión.');

    [$workflow] = reviewFixture();
    $missingReason = $workflow->execute(reviewCommand(
        decision: ReviewDecision::REJECT,
        reason: '  ',
    ));
    $assert($missingReason->status === ReviewOutcomeStatus::INVALID_INPUT, 'Motivo vacío activa entrada inválida.');
});

$test('WHITEBOX', 'WB-02', 'cubre salidas del repositorio SAVED, CONFLICT, NOT_FOUND y FAILED', static function () use ($assert): void {
    $cases = [
        [DecisionWriteStatus::SAVED, ReviewDecision::VALIDATE, ReviewOutcomeStatus::VALIDATED],
        [DecisionWriteStatus::SAVED, ReviewDecision::REJECT, ReviewOutcomeStatus::REJECTED],
        [DecisionWriteStatus::CONFLICT, ReviewDecision::VALIDATE, ReviewOutcomeStatus::CONFLICT],
        [DecisionWriteStatus::NOT_FOUND, ReviewDecision::VALIDATE, ReviewOutcomeStatus::NOT_FOUND],
        [DecisionWriteStatus::FAILED, ReviewDecision::VALIDATE, ReviewOutcomeStatus::PERSISTENCE_FAILURE],
    ];

    foreach ($cases as [$writeStatus, $decision, $expected]) {
        [$workflow, , $repository] = reviewFixture(writeStatus: $writeStatus);
        $command = reviewCommand(
            decision: $decision,
            reason: $decision === ReviewDecision::REJECT ? 'No conforme' : '',
        );

        $assert(
            $workflow->execute($command)->status === $expected,
            "El estado de escritura {$writeStatus->value} debe mapear a {$expected->value}.",
        );
        $assert($repository->writeCalls === 1, 'Cada resultado de persistencia debe provenir del puerto.');
    }
});

$test('BLACKBOX', 'BB-01', 'clases equivalentes de decisión válida producen validar o rechazar', static function () use ($assert): void {
    [$validateWorkflow, , $validateRepository] = reviewFixture();
    [$rejectWorkflow, , $rejectRepository] = reviewFixture();

    $valid = $validateWorkflow->execute(reviewCommand());
    $rejected = $rejectWorkflow->execute(reviewCommand(
        decision: ReviewDecision::REJECT,
        reason: 'Revisión profesional requerida',
    ));

    $assert($valid->status === ReviewOutcomeStatus::VALIDATED, 'Entrada válida de validación entrega estado validado.');
    $assert($rejected->status === ReviewOutcomeStatus::REJECTED, 'Entrada válida de rechazo entrega estado rechazado.');
    $assert(count($validateRepository->inner->decisions()) === 1, 'Validación registra exactamente una decisión.');
    $assert(count($rejectRepository->inner->decisions()) === 1, 'Rechazo registra exactamente una decisión.');
});

$test('BLACKBOX', 'BB-02', 'límites de versión 1, cero y versión desactualizada', static function () use ($assert): void {
    [$workflow] = reviewFixture(result: specimenResult(version: 1));
    $minimumAccepted = $workflow->execute(reviewCommand(version: 1));
    $assert($minimumAccepted->status === ReviewOutcomeStatus::VALIDATED, 'Versión mínima válida 1 permite decidir.');

    [$workflow] = reviewFixture();
    $zero = $workflow->execute(reviewCommand(version: 0));
    $assert($zero->status === ReviewOutcomeStatus::INVALID_INPUT, 'Versión 0 está fuera del límite permitido.');

    [$workflow] = reviewFixture(result: specimenResult(version: 2));
    $stale = $workflow->execute(reviewCommand(version: 1));
    $assert($stale->status === ReviewOutcomeStatus::CONFLICT, 'Una versión menor a la vigente produce conflicto.');
});

$test('BLACKBOX', 'BB-03', 'omisiones de motivo, contexto y actor no confirman decisiones', static function () use ($assert): void {
    [$workflow, , $repository] = reviewFixture();
    $noReason = $workflow->execute(reviewCommand(decision: ReviewDecision::REJECT));
    $assert($noReason->status === ReviewOutcomeStatus::INVALID_INPUT, 'La omisión del motivo bloquea el rechazo.');
    $assert($repository->writeCalls === 0, 'No se intenta persistir sin motivo.');

    foreach (['orderItemId', 'examinationId', 'specimenId'] as $missingField) {
        $result = specimenResult(...[$missingField => '']);
        [$workflow] = reviewFixture(result: $result);
        $outcome = $workflow->execute(reviewCommand());
        $assert($outcome->status === ReviewOutcomeStatus::INVALID_CONTEXT, "La omisión de {$missingField} bloquea la decisión.");
    }

    [$workflow, , $repository] = reviewFixture();
    $missingActor = $workflow->execute(reviewCommand(actorId: ' '));
    $assert($missingActor->status === ReviewOutcomeStatus::INVALID_INPUT, 'La omisión del bioquímico se rechaza.');
    $assert($repository->writeCalls === 0, 'No se persiste una decisión sin identidad del actor.');
});

$test('BLACKBOX', 'BB-04', 'errores de hospital, concurrencia y persistencia permanecen distinguibles', static function () use ($assert): void {
    [$workflow, , $repository] = reviewFixture();
    $foreignHospital = $workflow->execute(reviewCommand(hospitalId: 'HOSPITAL-B'));
    $assert($foreignHospital->status === ReviewOutcomeStatus::NOT_FOUND, 'Otro hospital no revela el resultado.');
    $assert($repository->writeCalls === 0, 'El resultado de otro hospital no se modifica.');

    $inner = new InMemoryLabResultDecisionRepository();
    $inner->seed(specimenResult());
    $racingWorkflow = new ReviewPendingLabResult(
        new TestReviewerAuthorization(),
        new TestClock(),
        new RacingDecisionRepository($inner),
    );
    $race = $racingWorkflow->execute(reviewCommand());
    $assert($race->status === ReviewOutcomeStatus::CONFLICT, 'Cambio concurrente se comunica como conflicto.');
    $assert($inner->decisions() === [], 'La versión obsoleta no deja auditoría.');

    [$workflow, , $failedRepository] = reviewFixture(writeStatus: DecisionWriteStatus::FAILED);
    $failure = $workflow->execute(reviewCommand());
    $assert($failure->status === ReviewOutcomeStatus::PERSISTENCE_FAILURE, 'Falla técnica no se confunde con rechazo clínico.');
    $assert($failedRepository->inner->decisions() === [], 'Falla técnica no crea una decisión parcial.');
});

echo "\n";
foreach ($groupTotals as $group => $count) {
    echo sprintf("%s: %d pruebas\n", $group, $count);
}
echo sprintf("RESULTADO: %d/%d pruebas aprobadas; %d pruebas fallidas.\n", $passed, $total, $failed);

if ($failed > 0) {
    exit(1);
}

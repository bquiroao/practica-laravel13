<?php

declare(strict_types=1);

require_once __DIR__ . '/../../semana-02/ejemplo-dip/despues/ReviewWorkflow.php';
require_once __DIR__ . '/../../semana-02/ejemplo-dip/despues/InMemoryLabResultDecisionRepository.php';

final class RoleBasedAuthorization implements ReviewerAuthorization
{
    /** @param array<string, list<string>> $biochemistsByHospital */
    public function __construct(private array $biochemistsByHospital)
    {
    }

    public function canReview(string $actorId, string $hospitalId): bool
    {
        return in_array($actorId, $this->biochemistsByHospital[$hospitalId] ?? [], true);
    }
}

final class WalkthroughClock implements Clock
{
    private int $tick = 0;

    public function now(): DateTimeImmutable
    {
        return (new DateTimeImmutable('2026-10-27T09:00:00-06:00'))->modify('+' . $this->tick++ . ' minutes');
    }
}

$repository = new InMemoryLabResultDecisionRepository();
$seed = static function (string $id, string $hospital = 'HOSP-A', int $version = 1, string $item = 'ITEM-1') use ($repository): void {
    $repository->seed(new LabResult($id, $hospital, $item, 'EXAM-GLU', 'SPEC-' . $id, $version, '5.4', 'mmol/L', LabResultStatus::PENDING));
};
foreach (['RES-1', 'RES-2', 'RES-3', 'RES-4', 'RES-5', 'RES-6'] as $id) {
    $seed($id);
}
$seed('RES-OTRO', 'HOSP-B');
$repository->seed(new LabResult('RES-SIN-CTX', 'HOSP-A', '', 'EXAM-GLU', 'SPEC-X', 1, '5.4', 'mmol/L', LabResultStatus::PENDING));

$useCase = new ReviewPendingLabResult(
    new RoleBasedAuthorization(['HOSP-A' => ['BIO-01', 'BIO-02']]),
    new WalkthroughClock(),
    $repository,
);

$steps = 0;
$step = static function (string $title, string $role, ReviewOutcome $outcome, string $expected) use (&$steps): void {
    $steps++;
    $line = sprintf('%2d. %s | rol: %s | resultado: %s', $steps, $title, $role, $outcome->status->value);
    if ($outcome->record !== null) {
        $line .= sprintf(' | actor=%s hospital=%s momento=%s', $outcome->record->actorId, $outcome->record->hospitalId, $outcome->record->decidedAt->format('H:i'));
    }
    echo $line . PHP_EOL;
    if ($outcome->status->value !== $expected) {
        fwrite(STDERR, "FALLO: se esperaba {$expected}" . PHP_EOL);
        exit(1);
    }
};

echo 'RECORRIDO: revisión, validación o rechazo de un resultado por bioquímico' . PHP_EOL;
echo str_repeat('-', 72) . PHP_EOL;

$step('Bioquímico valida RES-1', 'BIO-01', $useCase->execute(new ReviewCommand('RES-1', 1, 'BIO-01', 'HOSP-A', ReviewDecision::VALIDATE)), 'VALIDATED');
$step('Bioquímico rechaza RES-2 con motivo', 'BIO-02', $useCase->execute(new ReviewCommand('RES-2', 1, 'BIO-02', 'HOSP-A', ReviewDecision::REJECT, 'Muestra hemolizada')), 'REJECTED');
$step('Rechazo sin motivo en RES-3', 'BIO-01', $useCase->execute(new ReviewCommand('RES-3', 1, 'BIO-01', 'HOSP-A', ReviewDecision::REJECT)), 'INVALID_INPUT');
$step('Usuario sin permiso intenta validar RES-3', 'TEC-09', $useCase->execute(new ReviewCommand('RES-3', 1, 'TEC-09', 'HOSP-A', ReviewDecision::VALIDATE)), 'DENIED');
$step('Resultado de otro hospital', 'BIO-01', $useCase->execute(new ReviewCommand('RES-OTRO', 1, 'BIO-01', 'HOSP-A', ReviewDecision::VALIDATE)), 'NOT_FOUND');
$step('Resultado con contexto incompleto', 'BIO-01', $useCase->execute(new ReviewCommand('RES-SIN-CTX', 1, 'BIO-01', 'HOSP-A', ReviewDecision::VALIDATE)), 'INVALID_CONTEXT');
$step('Volver a decidir RES-1 (ya validado)', 'BIO-02', $useCase->execute(new ReviewCommand('RES-1', 1, 'BIO-02', 'HOSP-A', ReviewDecision::REJECT, 'Otro criterio')), 'INVALID_STATE');
$step('Versión esperada desactualizada en RES-4', 'BIO-01', $useCase->execute(new ReviewCommand('RES-4', 2, 'BIO-01', 'HOSP-A', ReviewDecision::VALIDATE)), 'CONFLICT');
$repository->failNextWrite();
$step('Falla de escritura al validar RES-5', 'BIO-01', $useCase->execute(new ReviewCommand('RES-5', 1, 'BIO-01', 'HOSP-A', ReviewDecision::VALIDATE)), 'PERSISTENCE_FAILURE');
$step('Reintento de RES-5 tras la falla', 'BIO-01', $useCase->execute(new ReviewCommand('RES-5', 1, 'BIO-01', 'HOSP-A', ReviewDecision::VALIDATE)), 'VALIDATED');

echo str_repeat('-', 72) . PHP_EOL;

$checks = [
    'RES-1 quedó validado y su valor no cambió' => $repository->findById('RES-1')?->status === LabResultStatus::VALIDATED && $repository->findById('RES-1')?->value === '5.4',
    'RES-2 quedó rechazado' => $repository->findById('RES-2')?->status === LabResultStatus::REJECTED,
    'RES-3 sigue pendiente (entrada inválida y denegación no cambian estado)' => $repository->findById('RES-3')?->status === LabResultStatus::PENDING,
    'RES-OTRO sigue pendiente (otro hospital)' => $repository->findById('RES-OTRO')?->status === LabResultStatus::PENDING,
    'RES-4 sigue pendiente tras el conflicto' => $repository->findById('RES-4')?->status === LabResultStatus::PENDING,
    'La falla de escritura no dejó decisión parcial; el reintento sí' => count(array_filter($repository->decisions(), static fn (ReviewDecisionRecord $d): bool => $d->resultId === 'RES-5')) === 1,
    'Solo hay 3 decisiones registradas (RES-1, RES-2, RES-5)' => count($repository->decisions()) === 3,
    'El rechazo conservó su motivo' => array_values(array_filter($repository->decisions(), static fn (ReviewDecisionRecord $d): bool => $d->resultId === 'RES-2'))[0]->reason === 'Muestra hemolizada',
];
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) {
        exit(1);
    }
}

echo sprintf('OK: %d pasos y %d comprobaciones finales.%s', $steps, count($checks), PHP_EOL);

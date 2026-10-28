<?php

declare(strict_types=1);

require __DIR__ . '/../src/LabResultWorkflow.php';

final class RecordingPendingResultRepository implements PendingResultRepository
{
    public int $reads = 0;
    public int $initialWrites = 0;
    public int $correctionWrites = 0;

    public function __construct(private InMemoryPendingResultRepository $inner)
    {
    }

    public function currentFor(string $orderItemId): ?LabResultRecord
    {
        $this->reads++;
        return $this->inner->currentFor($orderItemId);
    }

    public function saveInitial(PendingLabResult $result): bool
    {
        $this->initialWrites++;
        return $this->inner->saveInitial($result);
    }

    public function appendCorrection(
        string $expectedCurrentVersionId,
        PendingLabResult $corrected,
    ): bool {
        $this->correctionWrites++;
        return $this->inner->appendCorrection($expectedCurrentVersionId, $corrected);
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }

    echo "PASS: {$message}\n";
};

$repositoryFactories = [
    'memoria' => static fn (): PendingResultRepository => new InMemoryPendingResultRepository(),
    'decorador de registro' => static fn (): PendingResultRepository => new RecordingPendingResultRepository(
        new InMemoryPendingResultRepository(),
    ),
];

foreach ($repositoryFactories as $adapterName => $makeRepository) {
    $repository = $makeRepository();
    $initial = PendingLabResult::capture('V1', 'ITEM-1', '5.2', 'tecnico-1');
    $assert($repository->currentFor('ITEM-1') === null, "{$adapterName}: el ítem comienza sin resultado");
    $assert($repository->saveInitial($initial), "{$adapterName}: guarda primera versión pendiente");
    $assert(
        !$repository->saveInitial(PendingLabResult::capture('V2', 'ITEM-1', '5.3', 'tecnico-2')),
        "{$adapterName}: no reemplaza silenciosamente una captura vigente",
    );

    $corrected = $initial->corrected('V2', '5.3', 'Corrección de transcripción', 'tecnico-2');
    $assert(
        $repository->appendCorrection('V1', $corrected),
        "{$adapterName}: añade corrección si coincide la versión esperada",
    );

    $current = $repository->currentFor('ITEM-1');
    $assert($current instanceof PendingLabResult, "{$adapterName}: la versión corregida sigue pendiente");
    $assert($current?->version()->versionNumber === 2, "{$adapterName}: incrementa número de versión");
    $assert($current?->version()->previousVersionId === 'V1', "{$adapterName}: enlaza la versión anterior");
    $assert(
        $current?->version()->correctionReason === 'Corrección de transcripción',
        "{$adapterName}: conserva el motivo",
    );
    $assert(
        !$repository->appendCorrection('V1', $corrected),
        "{$adapterName}: rechaza reintento obsoleto sin alterar la versión actual",
    );
    $assert(
        $repository->currentFor('ITEM-1')?->version()->versionId === 'V2',
        "{$adapterName}: el conflicto conserva V2 como versión vigente",
    );
}

$serviceRepository = new InMemoryPendingResultRepository();
$service = new CaptureOrCorrectPendingResult($serviceRepository);
$capture = $service->capture('CAP-1', 'ITEM-2', 'Negativo', 'tecnico-3');
$assert($capture->status === OperationStatus::SUCCESS, 'captura el primer valor pendiente');
$assert($capture->result?->version()->versionNumber === 1, 'la captura inicia en versión 1');
$assert(
    $service->capture('CAP-2', 'ITEM-2', 'Positivo', 'tecnico-3')->status === OperationStatus::CONFLICT,
    'no reemplaza el resultado vigente con una captura duplicada',
);
$assert(
    $service->correct('ITEM-2', 'CAP-1', 'CAP-2', 'Positivo', '', 'tecnico-3')->status === OperationStatus::REJECTED,
    'rechaza corrección sin motivo',
);
$assert(
    $service->correct('ITEM-2', 'CAP-1', 'CAP-2', '   ', 'Dato transcrito incorrectamente', 'tecnico-3')->status === OperationStatus::REJECTED,
    'rechaza valor corregido vacío',
);
$correct = $service->correct(
    'ITEM-2',
    'CAP-1',
    'CAP-2',
    'Positivo',
    'Dato transcrito incorrectamente',
    'tecnico-3',
);
$assert($correct->status === OperationStatus::SUCCESS, 'corrige resultado pendiente con motivo');
$assert($correct->result?->version()->previousVersionId === 'CAP-1', 'la corrección enlaza la captura original');

$validated = new ValidatedLabResult(new ResultVersion(
    'VAL-1',
    'ITEM-3',
    '3.4',
    ResultState::VALIDATED,
    1,
    null,
    null,
    'bioquimico-1',
));
$validatedRepository = new InMemoryPendingResultRepository([$validated]);
$validatedService = new CaptureOrCorrectPendingResult($validatedRepository);
$assert(
    $validatedService->correct('ITEM-3', 'VAL-1', 'VAL-2', '3.5', 'Rectificación', 'tecnico-4')->status
        === OperationStatus::REJECTED,
    'el flujo de pendientes no modifica un resultado validado',
);

$recordingRepository = new RecordingPendingResultRepository(new InMemoryPendingResultRepository());
$recordingService = new CaptureOrCorrectPendingResult($recordingRepository);
$recordingResult = $recordingService->capture('REC-1', 'ITEM-4', 'Reactivo', 'tecnico-5');
$assert($recordingResult->status === OperationStatus::SUCCESS, 'el servicio usa el contrato sustituible');
$assert($recordingRepository->reads === 1 && $recordingRepository->initialWrites === 1, 'el decorador observa el mismo flujo sin alterar su resultado');

echo "OK: {$assertions} aserciones.\n";

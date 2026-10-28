<?php

declare(strict_types=1);

// Prototipo del contrato API: un adaptador HTTP simulado (sin servidor) sobre el caso de uso real de la Semana 4.
// El adaptador y su almacén de idempotencia viven solo en este script; el módulo actual no los tiene.

require_once __DIR__ . '/../../semana-04/ejemplo/bootstrap.php';

use LabResults\Application\CaptureOrCorrectResult;
use LabResults\Application\OperationStatus;
use LabResults\Application\ResultIdentifierGenerator;
use LabResults\Domain\PendingLabResult;
use LabResults\Domain\ResultState;
use LabResults\Domain\ResultVersion;
use LabResults\Domain\ValidatedLabResult;
use LabResults\Persistence\InMemoryLabResultRepository;

final class SequentialIds implements ResultIdentifierGenerator
{
    private int $n = 0;

    public function generate(): string
    {
        return sprintf('RES-%04d', ++$this->n);
    }
}

/** Adaptador propuesto: el actor viene de la sesión y no del cuerpo; la clave de idempotencia repite respuestas. */
final class ProposedApi
{
    /** @var array<string, array{hash: string, response: array{int, array}}> */
    private array $keys = [];

    public function __construct(private CaptureOrCorrectResult $useCase)
    {
    }

    /** @return array{int, array} */
    public function post(string $actor, ?string $key, array $body): array
    {
        if ($key === null || $key === '' || strlen($key) > 64) {
            return [400, ['title' => 'Idempotency-Key obligatoria']];
        }
        $hash = hash('sha256', $actor . json_encode($body, JSON_THROW_ON_ERROR));
        if (isset($this->keys[$key])) {
            return $this->keys[$key]['hash'] === $hash
                ? $this->keys[$key]['response']
                : [409, ['title' => 'La clave ya se usó con otro contenido']];
        }

        $response = $this->dispatch($actor, $body);
        if ($response[0] < 500) {
            $this->keys[$key] = ['hash' => $hash, 'response' => $response];
        }

        return $response;
    }

    /** @return array{int, array} */
    private function dispatch(string $actor, array $body): array
    {
        $operation = isset($body['expectedVersionId'])
            ? $this->useCase->correct(
                (string) $body['orderItemId'],
                (string) $body['expectedVersionId'],
                (string) ($body['value'] ?? ''),
                (string) ($body['reason'] ?? ''),
                $actor,
            )
            : $this->useCase->capture((string) $body['orderItemId'], (string) ($body['value'] ?? ''), $actor);

        $code = match ($operation->status) {
            OperationStatus::CAPTURED => 201,
            OperationStatus::CORRECTED => 201,
            OperationStatus::NOT_FOUND => 404,
            OperationStatus::CONFLICT => 409,
            // REJECTED mezcla entrada inválida (422) con "ya validado" (409); se distingue por el registro actual.
            OperationStatus::REJECTED => $operation->result instanceof ValidatedLabResult ? 409 : 422,
        };

        return [$code, ['status' => $operation->status->value, 'message' => $operation->message]];
    }
}

$checks = 0;
$failed = 0;
$check = static function (bool $ok, string $message) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    echo ($ok ? 'PASS' : 'FAIL') . ": {$message}\n";
};
$step = static function (int $n, string $title): void {
    echo "\nPaso {$n}. {$title}\n";
};

$validated = new ValidatedLabResult(new ResultVersion(
    'RES-VAL', 'ITEM-VAL', '7.2', ResultState::VALIDATED, 1, null, null, 'actor-1', new DateTimeImmutable(),
));
$repository = new InMemoryLabResultRepository([$validated]);
$useCase = new CaptureOrCorrectResult($repository, new SequentialIds());
$api = new ProposedApi($useCase);
$bare = new CaptureOrCorrectResult(new InMemoryLabResultRepository(), new SequentialIds());

$step(1, 'Captura nueva');
[$code, $res] = $api->post('tecnico-7', 'K1', ['orderItemId' => 'ITEM-1', 'value' => '5.1']);
$check($code === 201 && $res['status'] === 'captured', 'POST de un ítem nuevo responde 201');

$step(2, 'Repetición con la misma clave: no duplica');
[$code2, $res2] = $api->post('tecnico-7', 'K1', ['orderItemId' => 'ITEM-1', 'value' => '5.1']);
$check($code2 === 201 && $res2 === $res, 'la misma clave y contenido devuelve la respuesta original');
$check($repository->versionCount('ITEM-1') === 1, 'solo existe una versión del ítem');

$step(3, 'La misma clave con otro contenido');
[$code3] = $api->post('tecnico-7', 'K1', ['orderItemId' => 'ITEM-1', 'value' => '9.9']);
$check($code3 === 409, 'la misma clave con otro valor responde 409');

$step(4, 'Límite: sin idempotencia, un reintento por timeout se ve como conflicto');
$bare->capture('ITEM-2', '4.0', 'tecnico-7');
$retry = $bare->capture('ITEM-2', '4.0', 'tecnico-7');
$check(
    $retry->status === OperationStatus::CONFLICT,
    'LIMITE DEMOSTRADO: el caso de uso actual responde conflicto al repetir una captura idéntica',
);

$step(5, 'Corrección con versión vigente y con versión obsoleta');
$current = $repository->currentFor('ITEM-1');
$versionId = $current->version()->versionId;
[$code5] = $api->post('tecnico-7', 'K2', ['orderItemId' => 'ITEM-1', 'expectedVersionId' => $versionId, 'value' => '5.3', 'reason' => 'Error de digitación']);
$check($code5 === 201, 'una corrección con la versión vigente responde 201');
[$code6] = $api->post('tecnico-7', 'K3', ['orderItemId' => 'ITEM-1', 'expectedVersionId' => $versionId, 'value' => '5.4', 'reason' => 'Otra corrección']);
$check($code6 === 409, 'una corrección con la versión ya reemplazada responde 409');
$check($repository->versionCount('ITEM-1') === 2, 'la cadena tiene exactamente 2 versiones');

$step(6, 'Entrada inválida y ausencias');
[$code7] = $api->post('tecnico-7', 'K4', ['orderItemId' => 'ITEM-3', 'value' => '  ']);
$check($code7 === 422, 'un valor en blanco responde 422');
[$code8] = $api->post('tecnico-7', 'K5', ['orderItemId' => 'ITEM-X', 'expectedVersionId' => 'RES-0', 'value' => '1', 'reason' => 'x']);
$check($code8 === 404, 'corregir un ítem sin resultado responde 404');
[$code9] = $api->post('tecnico-7', null, ['orderItemId' => 'ITEM-4', 'value' => '1']);
$check($code9 === 400, 'sin Idempotency-Key responde 400');

$step(7, 'Resultado ya validado');
[$code10] = $api->post('tecnico-7', 'K6', ['orderItemId' => 'ITEM-VAL', 'expectedVersionId' => 'RES-VAL', 'value' => '7.3', 'reason' => 'Cambio']);
$check($code10 === 409, 'corregir un resultado validado responde 409, no 422');

$step(8, 'Límite: el estado REJECTED mezcla dos causas');
$blank = $bare->capture('ITEM-5', ' ', 'tecnico-7');
$onValidated = $useCase->correct('ITEM-VAL', 'RES-VAL', '7.3', 'Cambio', 'tecnico-7');
$check(
    $blank->status === OperationStatus::REJECTED && $onValidated->status === OperationStatus::REJECTED,
    'LIMITE DEMOSTRADO: entrada inválida y "ya validado" comparten el estado REJECTED; el adaptador los separa por inspección',
);

$step(9, 'El actor sale de la sesión');
$current = $repository->currentFor('ITEM-1')->version();
$check($current->enteredBy === 'tecnico-7', 'el autor guardado es el de la sesión; el cuerpo no lo trae');

echo "\n{$checks} comprobaciones, {$failed} fallidas.\n";
exit($failed === 0 ? 0 : 1);

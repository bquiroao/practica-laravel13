<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use MicroHis\Application\ReceiveSpecimen;
use MicroHis\Application\SpecimenIdentifierGenerator;
use MicroHis\Application\SpecimenRepository;
use MicroHis\Domain\ReceptionRequest;
use MicroHis\Domain\ReceptionStatus;
use MicroHis\Domain\SpecimenAcceptancePolicy;
use MicroHis\Domain\SpecimenReception;
use MicroHis\Domain\SpecimenType;
use MicroHis\Infrastructure\Identity\RandomSpecimenIdentifierGenerator;

final class FixedIdentifierGenerator implements SpecimenIdentifierGenerator
{
    public function generate(): string
    {
        return 'SP-TEST-0001';
    }
}

final class InMemorySpecimenRepository implements SpecimenRepository
{
    /** @var list<SpecimenReception> */
    public array $saved = [];

    public function __construct(private bool $fail = false)
    {
    }

    public function save(SpecimenReception $reception): void
    {
        if ($this->fail) {
            throw new RuntimeException('Fallo de persistencia simulado.');
        }

        $this->saved[] = $reception;
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

$request = static fn (
    bool $label = true,
    bool $container = true,
    bool $seal = true,
    ?string $reason = null,
): ReceptionRequest => new ReceptionRequest(
    'ORD-001',
    'PAT-001',
    'CBC',
    SpecimenType::BLOOD,
    $label,
    $container,
    $seal,
    $reason,
);

$repository = new InMemorySpecimenRepository();
$useCase = new ReceiveSpecimen(new SpecimenAcceptancePolicy(), $repository, new FixedIdentifierGenerator());
$accepted = $useCase->execute($request());
$assert($accepted->status === ReceptionStatus::ACCEPTED, 'camino feliz acepta una muestra conforme');
$assert(count($repository->saved) === 1, 'camino feliz persiste la recepción aceptada');
$assert($accepted->identifier === 'SP-TEST-0001', 'el caso de uso asigna un identificador');

$rejected = $useCase->execute($request(label: false));
$assert($rejected->status === ReceptionStatus::REJECTED, 'la regla de dominio rechaza una muestra sin etiqueta');
$assert(str_contains($rejected->reasons[0], 'identificación'), 'el rechazo contiene una razón explícita');
$assert(count($repository->saved) === 2, 'el rechazo controlado también queda persistido');

$damagedContainer = $useCase->execute($request(container: false));
$assert($damagedContainer->status === ReceptionStatus::REJECTED, 'la regla de dominio rechaza un recipiente dañado');

$unsealedBlood = $useCase->execute($request(seal: false));
$assert($unsealedBlood->status === ReceptionStatus::REJECTED, 'la regla específica rechaza un tubo de sangre sin sello íntegro');

$rejectedByOperator = $useCase->execute($request(reason: 'Discordancia confirmada con la orden.'));
$assert($rejectedByOperator->status === ReceptionStatus::REJECTED, 'se conserva el motivo de rechazo informado por el operador');

$identifierGenerator = new RandomSpecimenIdentifierGenerator();
$firstIdentifier = $identifierGenerator->generate();
$secondIdentifier = $identifierGenerator->generate();
$assert(
    preg_match('/^SP-[A-F0-9]{32}$/', $firstIdentifier) === 1 && $firstIdentifier !== $secondIdentifier,
    'el generador produce identificadores de 128 bits con formato y valores distintos',
);

$failingRepository = new InMemorySpecimenRepository(fail: true);
$failingUseCase = new ReceiveSpecimen(
    new SpecimenAcceptancePolicy(),
    $failingRepository,
    new FixedIdentifierGenerator(),
);
$persistenceFailed = false;
try {
    $failingUseCase->execute($request());
} catch (RuntimeException $exception) {
    $persistenceFailed = $exception->getMessage() === 'Fallo de persistencia simulado.';
}
$assert($persistenceFailed, 'un error de persistencia se propaga y no simula éxito');
$assert($failingRepository->saved === [], 'no se registra una recepción si falla el guardado');

$invalidRejectedRecordFailed = false;
try {
    new SpecimenReception(
        'SP-INVALID',
        'ORD-001',
        'PAT-001',
        'CBC',
        SpecimenType::BLOOD,
        ReceptionStatus::REJECTED,
        [],
        new DateTimeImmutable(),
    );
} catch (InvalidArgumentException) {
    $invalidRejectedRecordFailed = true;
}
$assert($invalidRejectedRecordFailed, 'el dominio impide un rechazo sin motivo');

echo "OK: {$assertions} aserciones.\n";

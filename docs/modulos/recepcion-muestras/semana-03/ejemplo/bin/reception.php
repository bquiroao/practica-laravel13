<?php

declare(strict_types=1);

use MicroHis\Application\ReceiveSpecimen;
use MicroHis\Domain\SpecimenAcceptancePolicy;
use MicroHis\Infrastructure\Identity\RandomSpecimenIdentifierGenerator;
use MicroHis\Persistence\PdoSpecimenRepository;
use MicroHis\Presentation\Cli\ReceiveSpecimenCommand;

require_once __DIR__ . '/../bootstrap.php';

$usage = 'Uso: php bin/reception.php --order=ORD-001 --patient=PAT-001 --exam=CBC --type=blood --label=yes --container=yes --seal=yes [--reject="motivo"]';

try {
    $options = getopt('', [
        'order:',
        'patient:',
        'exam:',
        'type:',
        'label:',
        'container:',
        'seal:',
        'reject:',
    ]);

    $dsn = getenv('DATABASE_DSN') ?: 'sqlite:' . __DIR__ . '/../reception.sqlite';
    $connection = new PDO(
        $dsn,
        getenv('DATABASE_USER') ?: null,
        getenv('DATABASE_PASSWORD') ?: null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );

    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('No fue posible leer el esquema de persistencia.');
    }
    $connection->exec($schema);

    $command = new ReceiveSpecimenCommand(new ReceiveSpecimen(
        new SpecimenAcceptancePolicy(),
        new PdoSpecimenRepository($connection),
        new RandomSpecimenIdentifierGenerator(),
    ));

    exit($command->run($options));
} catch (Throwable $exception) {
    fwrite(STDERR, "ERROR: {$exception->getMessage()}\n{$usage}\n");
    exit(1);
}

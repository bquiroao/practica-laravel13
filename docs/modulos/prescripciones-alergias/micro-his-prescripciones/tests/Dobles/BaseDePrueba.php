<?php

declare(strict_types=1);

use Prescripciones\Persistencia\Pdo\FabricaConexion;

/**
 * Base PostgreSQL recién instalada (schema.sql + datos-demo.sql). Si no hay PRESCRIPCIONES_DB_DSN, omite la prueba.
 * @return array{0: \PDO, 1: array<string, mixed>}
 */
function baseLimpia(): array
{
    $dsn = getenv('PRESCRIPCIONES_DB_DSN');
    if ($dsn === false || $dsn === '') {
        omitir('sin PRESCRIPCIONES_DB_DSN');
    }
    $config = require dirname(__DIR__, 2) . '/config/config.php';
    $config['persistencia'] = 'pdo';
    $pdo = FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave']);
    $pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql'));
    $pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/datos-demo.sql'));

    return [$pdo, $config];
}

function contar(\PDO $pdo, string $tabla): int
{
    return (int) $pdo->query("SELECT count(*) FROM $tabla")->fetchColumn();
}

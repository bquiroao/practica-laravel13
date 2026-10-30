<?php

declare(strict_types=1);

/*
 * Crea (o recrea) el esquema «prescripciones» y carga los datos de demostración.
 *   PRESCRIPCIONES_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_prescripciones" php bin/instalar-base.php
 */

$config = require dirname(__DIR__) . '/config/config.php';
$pdo = new \PDO($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
instalarBase($pdo);
echo "Esquema «prescripciones» creado y datos de demostración cargados." . PHP_EOL;

function instalarBase(\PDO $pdo): void
{
    $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
    $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/datos-demo.sql'));
    $pdo->exec('SET search_path TO prescripciones');
}

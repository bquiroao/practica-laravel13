<?php

declare(strict_types=1);

/*
 * Crea (o recrea) el esquema «qa» y carga los datos de demostración.
 *   QA_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_qa" php bin/instalar-base.php
 * Las pruebas de integración incluyen este archivo y llaman a instalarBase() sobre su propia conexión.
 */

if (realpath($argv[0] ?? '') === __FILE__) {
    $config = require dirname(__DIR__) . '/config/config.php';
    $pdo = new \PDO($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    instalarBase($pdo);
    echo 'Esquema «qa» creado y datos de demostración cargados.' . PHP_EOL;
}

function instalarBase(\PDO $pdo): void
{
    $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
    $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/datos-demo.sql'));
    $pdo->exec("SET search_path TO qa; SET TIME ZONE 'UTC'");
}

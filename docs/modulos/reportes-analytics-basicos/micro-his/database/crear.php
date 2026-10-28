<?php
declare(strict_types=1);

// Crea las tablas y carga los datos ficticios en las dos bases: principal (micro_his) y prueba (micro_his_test).
// Uso: php database/crear.php
require __DIR__ . '/../src/autoload.php';

use MicroHis\Domain\DatosNoDisponibles;
use MicroHis\Persistence\ConexionPdo;

$esquema = file_get_contents(__DIR__ . '/esquema.sql');
$datos = file_get_contents(__DIR__ . '/datos.sql');

foreach (['principal', 'prueba'] as $base) {
    try {
        $pdo = (new ConexionPdo(__DIR__ . '/../config/app.ini', $base))->pdo();
        $pdo->exec($esquema);
        $pdo->exec($datos);
        $camas = $pdo->query('SELECT count(*) FROM bed WHERE enabled')->fetchColumn();
        $estancias = $pdo->query('SELECT count(*) FROM bed_stay')->fetchColumn();
        echo "[{$base}] listo: {$camas} camas habilitadas y {$estancias} estancias cargadas\n";
    } catch (DatosNoDisponibles $e) {
        fwrite(STDERR, "[{$base}] {$e->getMessage()}. ¿Está corriendo «docker compose up -d»?\n");
        exit(1);
    }
}

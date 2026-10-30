<?php
declare(strict_types=1);

// Uso (desde micro-his/): php scripts/crear-esquema.php [--datos]
// Crea micro_alergias_test si falta y deja el esquema en micro_alergias y micro_alergias_test.
require __DIR__ . '/../autoload.php';

use Alergias\Persistencia\FabricaConexion;

$conDatos = in_array('--datos', $argv, true);
$admin = FabricaConexion::desdeEntorno('postgres');
$existe = $admin->prepare('SELECT 1 FROM pg_database WHERE datname = :nombre');
$existe->execute(['nombre' => 'micro_alergias_test']);
if (!$existe->fetchColumn()) {
    $admin->prepare('CREATE DATABASE micro_alergias_test')->execute();
}

foreach (['micro_alergias', 'micro_alergias_test'] as $base) {
    $pdo = FabricaConexion::desdeEntorno($base);
    foreach (require __DIR__ . '/esquema.php' as $sentencia) {
        $pdo->prepare($sentencia)->execute();
    }
    if ($conDatos && $base === 'micro_alergias') {
        foreach (require __DIR__ . '/datos-demo.php' as $sentencia) {
            $pdo->prepare($sentencia)->execute();
        }
    }
    echo "Esquema listo en {$base}" . ($conDatos && $base === 'micro_alergias' ? ' (con datos demo)' : '') . PHP_EOL;
}

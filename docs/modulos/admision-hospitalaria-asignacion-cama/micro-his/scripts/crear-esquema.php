<?php
declare(strict_types=1);

// Crea la base (si falta), el esquema y, opcionalmente, los datos ficticios. Es PHP y no .sh para que Windows no lo rompa.
// Uso (desde micro-his/):
//   php scripts/crear-esquema.php --base=micro_admision --datos-ficticios
//   php scripts/crear-esquema.php --base=micro_admision_test --recrear --datos-ficticios

require __DIR__ . '/../autoload.php';

use Admision\Persistence\Pdo\ConexionPdo;
use Admision\Persistence\Pdo\EsquemaMicroHis;

$base = 'micro_admision';
$recrear = in_array('--recrear', $argv, true);
$sembrar = in_array('--datos-ficticios', $argv, true);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = substr($arg, strlen('--base='));
    }
}
if (preg_match('/^[a-z_]{3,40}\z/', $base) !== 1) {
    fwrite(STDERR, "Nombre de base inválido: {$base}" . PHP_EOL);
    exit(1);
}

try {
    $servidor = ConexionPdo::desdeEntorno('postgres');
    $existe = $servidor->prepare('SELECT 1 FROM pg_database WHERE datname = :base');
    $existe->execute([':base' => $base]);
    if ($existe->fetchColumn() === false) {
        // Un identificador no se puede pasar como parámetro: se validó arriba con una expresión regular.
        $servidor->prepare('CREATE DATABASE "' . $base . '"')->execute();
        echo "Base {$base} creada." . PHP_EOL;
    }
    $pdo = ConexionPdo::desdeEntorno($base);
    EsquemaMicroHis::crear($pdo, $recrear);
    echo "Esquema listo en {$base}" . ($recrear ? ' (recreado)' : '') . '.' . PHP_EOL;
    if ($sembrar) {
        EsquemaMicroHis::sembrar($pdo, require __DIR__ . '/../datos-ficticios.php');
        echo 'Datos ficticios cargados.' . PHP_EOL;
    }
} catch (PDOException $e) {
    fwrite(STDERR, 'No se pudo preparar la base: ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, '¿Está arriba el contenedor? docker compose ps debe mostrar «healthy».' . PHP_EOL);
    exit(1);
}

<?php
declare(strict_types=1);

// Crea la tabla lab_test en micro_lab y micro_lab_test. Uso: php scripts/crear-esquema.php
require __DIR__ . '/../bootstrap.php';

$config = require __DIR__ . '/../config.php';
foreach ([$config['base'], $config['base_pruebas']] as $base) {
    crearEsquema(\Lab\Persistence\Conexion::crear(['base' => $base] + $config));
    echo "Esquema creado en $base" . PHP_EOL;
}

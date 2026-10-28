<?php
declare(strict_types=1);

// Valores por defecto para el Docker local; se pueden cambiar con variables de entorno.
return [
    'host' => getenv('LAB_DB_HOST') ?: '127.0.0.1',
    'puerto' => (int) (getenv('LAB_DB_PORT') ?: 55441),
    'base' => getenv('LAB_DB_NAME') ?: 'micro_lab',
    'base_pruebas' => getenv('LAB_DB_TEST_NAME') ?: 'micro_lab_test',
    'usuario' => getenv('LAB_DB_USER') ?: 'postgres',
    'clave' => getenv('LAB_DB_PASSWORD') ?: 'lab_local_2026',
    'tenant' => 'HOSP-01',
    'repositorio' => getenv('LAB_REPOSITORIO') ?: 'pdo', // 'pdo' o 'memoria'
];

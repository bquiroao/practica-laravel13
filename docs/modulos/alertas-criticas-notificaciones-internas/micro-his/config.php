<?php
// Configuración local del Micro-HIS. Credenciales FICTICIAS de desarrollo, solo para el contenedor local.
declare(strict_types=1);

return [
    'host' => getenv('ALERTAS_DB_HOST') ?: '127.0.0.1',
    'puerto' => (int) (getenv('ALERTAS_DB_PUERTO') ?: 55444),
    'base' => getenv('ALERTAS_DB_BASE') ?: 'micro_alertas',
    'base_prueba' => getenv('ALERTAS_DB_BASE_PRUEBA') ?: 'micro_alertas_test',
    'usuario' => getenv('ALERTAS_DB_USUARIO') ?: 'alertas',
    'clave' => getenv('ALERTAS_DB_CLAVE') ?: 'alertas_local',
    'zona' => 'America/Guatemala',
];

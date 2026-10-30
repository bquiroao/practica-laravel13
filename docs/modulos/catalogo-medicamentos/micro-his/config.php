<?php
declare(strict_types=1);

// Configuración local. Las credenciales son SOLO de desarrollo (ficticias) y se pueden cambiar
// con variables de entorno. Nada de esto va a producción.
$entorno = static fn (string $nombre, string $porDefecto): string => getenv($nombre) !== false ? (string) getenv($nombre) : $porDefecto;

return [
    'bd' => [
        'host' => $entorno('CATALOGO_BD_HOST', '127.0.0.1'),
        'puerto' => (int) $entorno('CATALOGO_BD_PUERTO', '55445'),
        'base' => $entorno('CATALOGO_BD_NOMBRE', 'micro_medicamentos'),
        'base_prueba' => $entorno('CATALOGO_BD_PRUEBA', 'micro_medicamentos_test'),
        'usuario' => $entorno('CATALOGO_BD_USUARIO', 'catalogo'),
        'clave' => $entorno('CATALOGO_BD_CLAVE', 'catalogo_local'),
    ],
    'zona_horaria' => 'America/Guatemala',
    // Semana 4: identidad fija de demostración para la entrada web (sin autenticación todavía).
    'web' => ['hospital' => '11111111-1111-4111-8111-111111111111', 'usuario' => 'qf.norte'],
];

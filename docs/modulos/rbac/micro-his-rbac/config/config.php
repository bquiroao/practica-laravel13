<?php

declare(strict_types=1);

// Configuración leída de variables de entorno; ninguna credencial queda en el repositorio.
return [
    'db' => [
        'dsn' => getenv('MICROHIS_DB_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=micro_his_rbac',
        'usuario' => getenv('MICROHIS_DB_USUARIO') ?: 'postgres',
        'clave' => getenv('MICROHIS_DB_CLAVE') ?: '',
    ],
    'respaldo_auditoria' => getenv('MICROHIS_RESPALDO') ?: dirname(__DIR__) . '/var/auditoria-respaldo.jsonl',
    // Tokens de demostración (datos ficticios). En el HIS la identidad la da JwtAuth.
    'tokens_demo' => [
        'token-admin-oriente' => ['admin-01', 'hosp-oriente'],
        'token-ana' => ['ana', 'hosp-oriente'],
        'token-marta' => ['marta', 'hosp-oriente'],
        'token-admin-norte' => ['admin-02', 'hosp-norte'],
    ],
];

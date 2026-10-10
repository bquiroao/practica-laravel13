<?php

declare(strict_types=1);

// Configuración desde variables de entorno; ninguna credencial queda en el repositorio.
return [
    'db' => [
        'dsn' => getenv('NOTASSOAP_DB_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=micro_his_notas_soap',
        'usuario' => getenv('NOTASSOAP_DB_USUARIO') ?: 'postgres',
        'clave' => getenv('NOTASSOAP_DB_CLAVE') ?: '',
    ],
    // Tokens de demostración (datos ficticios). En el HIS la identidad la da JwtAuth (módulo 1).
    'tokens_demo' => [
        'token-mendez' => ['dra-mendez', 'hosp-oriente', ['medico']],
        'token-lopez' => ['dr-lopez', 'hosp-oriente', ['medico']],
        'token-ana' => ['enf-ana', 'hosp-oriente', ['enfermeria']],
    ],
];

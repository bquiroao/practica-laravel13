<?php

declare(strict_types=1);

// Configuración desde variables de entorno; ninguna credencial queda en el repositorio.
return [
    // 'pdo' (PostgreSQL) o 'memoria' (sin base de datos; cada petición empieza con los datos de demostración, salvo que se use NOTASSOAP_MEMORIA_ARCHIVO).
    'almacen' => getenv('NOTASSOAP_ALMACEN') ?: 'pdo',
    // Solo con 'memoria': archivo donde se conservan los datos entre peticiones (demostración). Vacío = no se conservan.
    'memoria_archivo' => getenv('NOTASSOAP_MEMORIA_ARCHIVO') ?: null,
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
    // Permisos por rol (datos de demostración). En el HIS los decide el módulo RBAC (ASII-02).
    'permisos_por_rol' => [
        'medico' => ['notas_soap.registrar', 'notas_soap.firmar', 'notas_soap.consultar'],
        'enfermeria' => ['notas_soap.consultar'],
    ],
];

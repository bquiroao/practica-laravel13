<?php

declare(strict_types=1);

// Configuración leída de variables de entorno: ninguna credencial ni clave queda en el repositorio.
$basicos = ['pacientes.registrar', 'pacientes.buscar', 'pacientes.leer', 'pacientes.editar'];

return [
    // 'pdo' = PostgreSQL; 'memoria' = adaptadores InMemory con los pacientes de database/datos-demo.php.
    'persistencia' => getenv('PACIENTES_PERSISTENCIA') ?: 'pdo',
    'db' => [
        'dsn' => getenv('PACIENTES_DB_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=micro_his_pacientes',
        'usuario' => getenv('PACIENTES_DB_USUARIO') ?: 'postgres',
        'clave' => getenv('PACIENTES_DB_CLAVE') ?: '',
    ],
    // Claves en base64 (32 bytes cada una). Se generan con: php bin/generar-claves.php
    'claves' => [
        'hash' => getenv('PACIENTES_CLAVE_HASH') ?: '',
        'cifrado' => getenv('PACIENTES_CLAVE_CIFRADO') ?: '',
    ],
    // Tokens de demostración (datos ficticios). En el HIS la identidad la entrega JwtAuth.
    'tokens_demo' => [
        'token-admision-oriente' => ['admision-01', 'hosp-oriente'],
        'token-clinico-oriente' => ['clinico-01', 'hosp-oriente'],
        'token-supervisor-oriente' => ['supervisor-01', 'hosp-oriente'],
        'token-admision-norte' => ['admision-02', 'hosp-norte'],
    ],
    // Provisional: en el HIS los decide el módulo RBAC a través del puerto PoliticaAcceso.
    'permisos' => [
        'admision-01|hosp-oriente' => $basicos,
        'clinico-01|hosp-oriente' => ['pacientes.buscar', 'pacientes.leer'],
        'supervisor-01|hosp-oriente' => [...$basicos, 'pacientes.confirmar_duplicado', 'pacientes.editar_restringido',
            'pacientes.proteger', 'pacientes.leer_protegido'],
        'admision-02|hosp-norte' => $basicos,
    ],
];

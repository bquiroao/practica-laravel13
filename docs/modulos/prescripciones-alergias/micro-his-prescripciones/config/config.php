<?php

declare(strict_types=1);

// Configuración leída de variables de entorno: ninguna credencial queda en el repositorio.
return [
    // «pdo» (PostgreSQL) o «memoria» (adaptadores InMemory con database/datos-demo.php; sin base).
    'persistencia' => getenv('PRESCRIPCIONES_PERSISTENCIA') ?: 'pdo',
    'db' => [
        'dsn' => getenv('PRESCRIPCIONES_DB_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=micro_his_prescripciones',
        'usuario' => getenv('PRESCRIPCIONES_DB_USUARIO') ?: 'postgres',
        'clave' => getenv('PRESCRIPCIONES_DB_CLAVE') ?: '',
    ],
    // Tokens de demostración (datos ficticios). En el HIS la identidad la entrega JwtAuth.
    'tokens_demo' => [
        'token-dr-lopez' => ['dr-lopez', 'hosp-norte'],
        'token-dr-ruiz' => ['dr-ruiz', 'hosp-norte'],
        'token-farmacia-norte' => ['farm-norte', 'hosp-norte'],
        'token-dr-sur' => ['dr-sur', 'hosp-sur'],
    ],
    // Provisional: en el HIS los decide RBAC detrás del puerto PoliticaAcceso. Clave «usuario|hospital».
    'permisos' => [
        'dr-lopez|hosp-norte' => ['prescripcion.emitir', 'prescripcion.override', 'prescripcion.consultar'],
        'dr-ruiz|hosp-norte' => ['prescripcion.emitir', 'prescripcion.consultar'],
        'farm-norte|hosp-norte' => ['prescripcion.consultar'],
        'dr-sur|hosp-sur' => ['prescripcion.emitir', 'prescripcion.override', 'prescripcion.consultar'],
    ],
];

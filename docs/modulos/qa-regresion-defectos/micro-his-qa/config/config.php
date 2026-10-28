<?php

declare(strict_types=1);

// Configuración leída de variables de entorno: ninguna credencial queda en el repositorio.
return [
    'db' => [
        'dsn' => getenv('QA_DB_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=micro_his_qa',
        'usuario' => getenv('QA_DB_USUARIO') ?: 'postgres',
        'clave' => getenv('QA_DB_CLAVE') ?: '',
    ],
    // Carpeta del almacén de evidencias (en el HIS, un almacén de objetos detrás del mismo puerto).
    'evidencias' => getenv('QA_EVIDENCIAS_DIR') ?: sys_get_temp_dir() . '/micro-his-qa-evidencias',
    // Tokens de demostración (datos ficticios). En el HIS la identidad la entrega JwtAuth.
    'tokens_demo' => [
        'token-ana-qa' => ['ana-qa', 'hosp-norte'],
        'token-lider-qa' => ['lider-qa', 'hosp-norte'],
        'token-tito-tester' => ['tito-tester', 'hosp-norte'],
        'token-dev-luis' => ['dev-luis', 'hosp-norte'],
        'token-producto' => ['producto', 'hosp-norte'],
        'token-tester-sur' => ['tester-sur', 'hosp-sur'],
    ],
    // Provisional: en el HIS los decide RBAC detrás del puerto PoliticaAcceso. Clave «usuario|tenant».
    'permisos' => [
        'ana-qa|hosp-norte' => ['qa.disenar', 'qa.consultar'],
        'lider-qa|hosp-norte' => ['qa.disenar', 'qa.aprobar', 'qa.planificar', 'qa.clasificar', 'qa.cerrar', 'qa.consultar', 'qa.ver_evidencia_clinica'],
        'tito-tester|hosp-norte' => ['qa.ejecutar', 'qa.consultar'],
        'dev-luis|hosp-norte' => ['qa.corregir', 'qa.consultar'],
        'producto|hosp-norte' => ['qa.consultar'],
        'tester-sur|hosp-sur' => ['qa.ejecutar', 'qa.consultar'],
    ],
];

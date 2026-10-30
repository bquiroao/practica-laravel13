<?php

declare(strict_types=1);

// Configuración por entorno. Los secretos llegan por variables de entorno; aquí solo los valores de demostración.
return [
    'persistencia' => getenv('E2E_PERSISTENCIA') ?: 'pdo',
    'db' => [
        'dsn' => getenv('E2E_DB_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=micro_his_e2e',
        'usuario' => getenv('E2E_DB_USUARIO') ?: 'postgres',
        'clave' => getenv('E2E_DB_CLAVE') ?: '',
    ],
    'evidencias' => getenv('E2E_EVIDENCIAS_DIR') ?: sys_get_temp_dir() . '/micro-his-e2e-evidencias',
    // R-09: política versionada. Cambiar un umbral es una versión nueva de la política, no una edición silenciosa.
    'politica_gate' => ['version' => 'gate-2026.1', 'umbral_no_criticos' => 0.95, 'max_inestables' => 2, 'antiguedad_maxima_horas' => 24,
        'vigencia_aprobacion_horas' => 72],                                   // semana 6: R-11
    // Identidad de demostración mientras el HIS no expone su JWT: token => [usuario, tenant].
    'tokens_demo' => [
        'token-ing-qa' => ['ing-qa', 'hosp-norte'],
        'token-pipeline' => ['pipeline-ci', 'hosp-norte'],
        'token-release' => ['resp-release', 'hosp-norte'],
        'token-dev' => ['dev-ana', 'hosp-norte'],
        'token-ops' => ['ops-luis', 'hosp-norte'],
        'token-pipeline-sur' => ['pipeline-ci', 'hosp-sur'],
    ],
    // R-13: el pipeline reporta y ejecuta, pero no evalúa el gate con su propio criterio ni planifica.
    'permisos' => [
        'ing-qa|hosp-norte' => ['e2e.disenar', 'e2e.planificar', 'e2e.consultar'],
        'pipeline-ci|hosp-norte' => ['e2e.versionar', 'e2e.ejecutar', 'e2e.reportar', 'e2e.consultar'],
        'resp-release|hosp-norte' => ['e2e.evaluar', 'e2e.consultar'],
        'dev-ana|hosp-norte' => ['e2e.versionar', 'e2e.consultar'],
        'ops-luis|hosp-norte' => ['e2e.desplegar', 'e2e.consultar'],      // semana 6: el operador despliega; el pipeline no
        'pipeline-ci|hosp-sur' => ['e2e.versionar', 'e2e.ejecutar', 'e2e.reportar', 'e2e.consultar'],
    ],
];

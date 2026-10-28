<?php

declare(strict_types=1);

// Configuración leída de variables de entorno: ninguna credencial queda en el repositorio.
return [
    // 'pdo' = PostgreSQL; 'memoria' = adaptadores InMemory de producción (demo sin base de datos).
    'persistencia' => getenv('AUDITORIA_PERSISTENCIA') ?: 'pdo',
    'db' => [
        'dsn' => getenv('AUDITORIA_DB_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=micro_his_auditoria',
        'usuario' => getenv('AUDITORIA_DB_USUARIO') ?: 'postgres',
        'clave' => getenv('AUDITORIA_DB_CLAVE') ?: '',
    ],
    'zona_horaria' => 'America/Guatemala',
    // Catálogo acordado con los módulos emisores (R-01).
    'catalogo' => [
        'pacientes' => ['PACIENTE_REGISTRADO', 'PACIENTE_ACTUALIZADO', 'PACIENTE_CONSULTADO', 'PACIENTE_BUSCADO'],
        'rbac' => ['ROL_ASIGNADO', 'ROL_REVOCADO', 'ACCESO_DENEGADO'],
        'citas' => ['CITA_CREADA', 'CITA_CANCELADA'],
        'auditoria' => ['AUDITORIA_CONSULTADA', 'INTEGRIDAD_VERIFICADA'],
    ],
    // Cada cuenta de servicio solo registra movimientos de su propio módulo (semana 2).
    'modulo_de_emisor' => ['svc-pacientes' => 'pacientes', 'svc-rbac' => 'rbac', 'svc-citas' => 'citas'],
    // Tokens de demostración (datos ficticios). En el HIS la identidad la entrega JwtAuth. '*' = toda la red.
    'tokens_demo' => [
        'token-svc-pacientes-oriente' => ['svc-pacientes', 'hosp-oriente'],
        'token-svc-pacientes-norte' => ['svc-pacientes', 'hosp-norte'],
        'token-svc-rbac-oriente' => ['svc-rbac', 'hosp-oriente'],
        'token-auditor-oriente' => ['auditor-oriente', 'hosp-oriente'],
        'token-superadmin' => ['superadmin', '*'],
    ],
    // Provisional: en el HIS los decide RBAC a través del puerto PoliticaAcceso. "actor|*" = alcance de red.
    'permisos' => [
        'svc-pacientes|hosp-oriente' => ['auditoria.registrar'],
        'svc-pacientes|hosp-norte' => ['auditoria.registrar'],
        'svc-rbac|hosp-oriente' => ['auditoria.registrar'],
        'auditor-oriente|hosp-oriente' => ['auditoria.consultar'],
        'superadmin|hosp-oriente' => ['auditoria.consultar'],
        'superadmin|hosp-norte' => ['auditoria.consultar'],
        'superadmin|*' => ['auditoria.consultar_red', 'auditoria.verificar'],
    ],
];

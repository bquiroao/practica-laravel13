<?php

declare(strict_types=1);

// Datos semilla para el adaptador InMemory. Mismos datos ficticios que datos-demo.sql.
return [
    'usuarios' => [
        'admin-01' => 'active',
        'admin-02' => 'active',
        'ana' => 'active',
        'marta' => 'active',
        'pedro' => 'active',
        'lucia' => 'active',
        'nuevo-01' => 'active',
        'jorge' => 'inactive',
    ],
    'permisos_por_rol' => [
        'admin_hospital' => ['roles.asignar', 'roles.revocar', 'auditoria.consultar'],
        'medico' => ['prescripciones.crear', 'signos_vitales.registrar', 'expediente.leer'],
        'enfermeria' => ['signos_vitales.registrar', 'expediente.leer'],
        'laboratorio' => ['resultados.registrar'],
    ],
    // [usuario, hospital, rol, estado, valido_desde, valido_hasta]
    'membresias' => [
        ['admin-01', 'hosp-oriente', 'admin_hospital', 'active', '2026-01-01T00:00:00-06:00', null],
        ['admin-02', 'hosp-norte', 'admin_hospital', 'active', '2026-01-01T00:00:00-06:00', null],
        ['ana', 'hosp-oriente', 'enfermeria', 'active', '2026-01-01T00:00:00-06:00', null],
        ['marta', 'hosp-oriente', 'medico', 'active', '2026-02-01T00:00:00-06:00', null],
        ['pedro', 'hosp-oriente', 'medico', 'active', '2026-01-01T00:00:00-06:00', '2026-06-30T00:00:00-06:00'],
        ['lucia', 'hosp-oriente', 'enfermeria', 'revoked', '2026-01-01T00:00:00-06:00', null],
    ],
];

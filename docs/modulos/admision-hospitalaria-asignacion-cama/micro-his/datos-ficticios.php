<?php
declare(strict_types=1);

// Datos FICTICIOS compartidos por el esquema de prueba, las pruebas y el adaptador InMemory.
// Pacientes solo por código; nunca nombres.
return [
    'camas' => [
        // id, hospital, código, servicio, tipo, aislamiento, operativa
        ['c0000000-0000-4000-8000-000000000201', 'HOSP-01', 'B-201', 'MEDICINA_INTERNA', 'GENERAL', false, true],
        ['c0000000-0000-4000-8000-000000000202', 'HOSP-01', 'B-202', 'MEDICINA_INTERNA', 'GENERAL', false, true],
        ['c0000000-0000-4000-8000-000000000203', 'HOSP-01', 'B-203', 'MEDICINA_INTERNA', 'GENERAL', false, false],
        ['c0000000-0000-4000-8000-000000000210', 'HOSP-01', 'B-210', 'MEDICINA_INTERNA', 'GENERAL', true, true],
        ['c0000000-0000-4000-8000-000000000101', 'HOSP-01', 'C-101', 'CIRUGIA', 'GENERAL', false, true],
        ['c0000000-0000-4000-8000-000000000301', 'HOSP-01', 'U-01', 'UCI', 'UCI', false, true],
        ['c0000000-0000-4000-8000-000000002201', 'HOSP-02', 'B-201', 'MEDICINA_INTERNA', 'GENERAL', false, true],
    ],
    'pacientes' => [
        // hospital, código, activo
        ['HOSP-01', 'PAC-00451', true],
        ['HOSP-01', 'PAC-00452', true],
        ['HOSP-01', 'PAC-00453', true],
        ['HOSP-01', 'PAC-00460', false],
        ['HOSP-02', 'PAC-00900', true],
    ],
    'usuarios' => [
        // usuario => [rol, hospital]
        'usr-adm-01' => ['ADMISIONISTA', 'HOSP-01'],
        'usr-sup-01' => ['SUPERVISOR_CAMAS', 'HOSP-01'],
        'usr-med-01' => ['MEDICO', 'HOSP-01'],
        'usr-adm-02' => ['ADMISIONISTA', 'HOSP-02'],
    ],
    'tokens' => [
        // token FICTICIO de la API (semana 5) => usuario. No son secretos ni sirven fuera del Micro-HIS.
        'tok-adm-01' => 'usr-adm-01',
        'tok-sup-01' => 'usr-sup-01',
        'tok-med-01' => 'usr-med-01',
        'tok-adm-02' => 'usr-adm-02',
    ],
];

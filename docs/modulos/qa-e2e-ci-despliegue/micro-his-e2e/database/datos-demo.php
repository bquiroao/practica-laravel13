<?php

declare(strict_types=1);

// Catálogo de escenarios E2E de demostración (los mismos que datos-demo.sql; INT-07 lo verifica).
// [tenant, id, título, módulo, crítico, requisitos, conjunto de datos sintético]
return [
    'escenarios' => [
        ['hosp-norte', 'E2E-101', 'Admitir paciente y asignar cama', 'admision', true, ['RF-01', 'RF-03'], 'SINT-ADMISION-01'],
        ['hosp-norte', 'E2E-102', 'Prescribir con alergia registrada', 'prescripciones', true, ['RF-05'], 'SINT-ALERGIA-01'],
        ['hosp-norte', 'E2E-103', 'Ingresar resultado de laboratorio', 'laboratorio', false, ['RF-07'], 'SINT-LAB-01'],
        ['hosp-norte', 'E2E-104', 'Imprimir epicrisis', 'expediente', false, ['RF-08'], 'SINT-EPICRISIS-01'],
        ['hosp-norte', 'E2E-105', 'Buscar paciente por documento', 'pacientes', false, ['RF-02'], 'SINT-PACIENTES-01'],
        ['hosp-norte', 'E2E-106', 'Agendar cita', 'citas', false, ['RF-09'], 'SINT-CITAS-01'],
        ['hosp-sur', 'E2E-101', 'Admitir paciente y asignar cama', 'admision', true, ['RF-01'], 'SINT-ADMISION-01'],
    ],
];

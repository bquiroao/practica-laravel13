<?php

declare(strict_types=1);

// Los MISMOS datos de demostración que datos-demo.sql, para el modo «memoria». La suite de contrato
// (CON-*-06 y CON-*-07) comprueba que los dos adaptadores devuelven lo mismo con estos datos.
return [
    'pacientes' => [
        'hosp-norte' => ['P-001', 'P-002', 'P-003', 'P-004', 'P-005', 'P-006', 'P-007'],
        'hosp-sur' => ['P-900'],
    ],
    // [código, nombre, tipo, severidad, estado, reacción] — P-003 no tiene ningún registro (no evaluado).
    'alergias' => [
        'P-001' => [['J01CA04', 'Amoxicilina', 'allergy', 'severe', 'active', 'urticaria generalizada']],
        'P-002' => [[null, 'Penicilina', 'allergy', 'moderate', 'active', 'exantema']],
        'P-004' => [['N02BA01', 'Ácido acetilsalicílico', 'allergy', 'life_threatening', 'active', 'anafilaxia']],
        'P-005' => [['J01CA04', 'Amoxicilina', 'allergy', 'mild', 'resolved', null]],
        'P-006' => [[null, 'penicilina', 'unknown', 'unknown', 'active', null]],
        'P-007' => [[null, '  AMOXICILINA ', 'allergy', 'moderate', 'active', 'prurito']],
    ],
    // [código, nombre, principio activo (código), principio activo, grupo, unidad, dosis máxima diaria, vías, activo]
    'medicamentos' => [
        ['AMOX500', 'Amoxicilina 500 mg', 'J01CA04', 'Amoxicilina', 'betalactamicos', 'mg', 3000.0, ['oral'], true],
        ['CEFA500', 'Cefalexina 500 mg', 'J01DB01', 'Cefalexina', 'betalactamicos', 'mg', 4000.0, ['oral'], true],
        ['PARA500', 'Paracetamol 500 mg', 'N02BE01', 'Paracetamol', 'anilidas', 'mg', 4000.0, ['oral', 'rectal'], true],
        ['IBU400', 'Ibuprofeno 400 mg', 'M01AE01', 'Ibuprofeno', 'aines', 'mg', 2400.0, ['oral'], true],
        ['RANI150', 'Ranitidina 150 mg', 'A02BA02', 'Ranitidina', 'antih2', 'mg', 300.0, ['oral'], false],
    ],
    'grupos' => ['j01ca04' => 'betalactamicos', 'j01ce01' => 'betalactamicos', 'penicilina' => 'betalactamicos', 'n02ba01' => 'aines', 'm01ae01' => 'aines'],
];

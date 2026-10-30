<?php
// Destinatarios por tenant → servicio → nivel. Usuarios FICTICIOS (nunca nombres reales).
// Nivel 0: enfermería de turno + médico tratante · 1: jefe de servicio · 2: coordinación médica de turno.
declare(strict_types=1);

return [
    'HOSP-GUA-01' => [
        'MED-INT' => [0 => ['enf.turno01', 'med.tratante01'], 1 => ['jefe.servicio01'], 2 => ['coord.medico01']],
        'PED' => [0 => [], 1 => ['jefe.servicio02'], 2 => ['coord.medico01']],
    ],
    'HOSP-XEL-02' => [
        'MED-INT' => [0 => ['enf.turno21'], 1 => ['jefe.servicio21'], 2 => ['coord.medico21']],
    ],
];

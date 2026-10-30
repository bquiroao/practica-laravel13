<?php

declare(strict_types=1);

// Pacientes ficticios de demostración: [actor, hospital, datos del formulario].
return [
    ['admision-01', 'hosp-oriente', ['nombres' => 'Ana Lucía', 'apellidos' => 'López Pérez', 'fecha_nacimiento' => '1985-03-14', 'sexo' => 'female',
        'telefono' => '+502 5555 1234', 'tipo_documento' => 'DPI', 'numero_documento' => '2547896320101']],
    ['admision-01', 'hosp-oriente', ['nombres' => 'Luis Fernando', 'apellidos' => 'García Ruiz', 'fecha_nacimiento' => '1978-11-02', 'sexo' => 'male',
        'tipo_documento' => 'DPI', 'numero_documento' => '1876543210901']],
    ['admision-01', 'hosp-oriente', ['nombres' => 'Juan', 'apellidos' => 'Desconocido', 'fecha_nacimiento' => '2000-01-01', 'sexo' => 'male']],
    ['admision-02', 'hosp-norte', ['nombres' => 'María José', 'apellidos' => 'Pérez Cano', 'fecha_nacimiento' => '1992-07-30', 'sexo' => 'female',
        'tipo_documento' => 'PASAPORTE', 'numero_documento' => 'X1234567', 'pais_documento' => 'MEX']],
];

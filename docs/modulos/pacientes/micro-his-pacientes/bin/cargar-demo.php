<?php

declare(strict_types=1);

/*
 * Carga pacientes ficticios A TRAVÉS de los casos de uso, para que el documento quede
 * cifrado con las claves configuradas y cada registro deje su evento de auditoría.
 *   php bin/cargar-demo.php
 */

use Pacientes\Aplicacion\Comandos\ComandoRegistrar;

require dirname(__DIR__) . '/bootstrap/contenedor.php';

$casos = crearCasosDeUsoPdo(require dirname(__DIR__) . '/config/config.php');

$pacientes = [
    ['admision-01', 'hosp-oriente', ['nombres' => 'Ana Lucía', 'apellidos' => 'López Pérez', 'fecha_nacimiento' => '1985-03-14', 'sexo' => 'female',
        'telefono' => '+502 5555 1234', 'tipo_documento' => 'DPI', 'numero_documento' => '2547896320101']],
    ['admision-01', 'hosp-oriente', ['nombres' => 'Luis Fernando', 'apellidos' => 'García Ruiz', 'fecha_nacimiento' => '1978-11-02', 'sexo' => 'male',
        'tipo_documento' => 'DPI', 'numero_documento' => '1876543210901']],
    ['admision-01', 'hosp-oriente', ['nombres' => 'Juan', 'apellidos' => 'Desconocido', 'fecha_nacimiento' => '2000-01-01', 'sexo' => 'male']],
    ['admision-02', 'hosp-norte', ['nombres' => 'María José', 'apellidos' => 'Pérez Cano', 'fecha_nacimiento' => '1992-07-30', 'sexo' => 'female',
        'tipo_documento' => 'PASAPORTE', 'numero_documento' => 'X1234567', 'pais_documento' => 'MEX']],
];

foreach ($pacientes as [$actor, $hospital, $datos]) {
    $r = $casos['registrar']->ejecutar(new ComandoRegistrar($actor, $hospital, $datos));
    echo sprintf('%-13s %-28s %s', $hospital, $datos['nombres'] . ' ' . $datos['apellidos'],
        $r->exitoso() ? $r->datos['codigo'] : $r->estado . ' ' . $r->error) . PHP_EOL;
}

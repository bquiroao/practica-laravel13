<?php

declare(strict_types=1);

/*
 * Semana 6 — Demostración del cambio práctico «paciente con identidad protegida», de punta a
 * punta por la API, sin base de datos (adaptadores InMemory).
 *   php bin/demo-proteccion.php
 */

use Pacientes\Presentacion\Http\Solicitud;

require dirname(__DIR__) . '/bootstrap/contenedor.php';

$config = ['persistencia' => 'memoria'] + (require dirname(__DIR__) . '/config/config.php');
$kernel = crearKernel(array_merge($config, ['tokens_demo' => $config['tokens_demo']]));

$cab = static fn (string $token, array $extra = []): array => $extra + ['X-Tenant-ID' => 'hosp-oriente', 'Authorization' => 'Bearer ' . $token];
$sofia = ['nombres' => 'Sofía', 'apellidos' => 'Morales Díaz', 'fecha_nacimiento' => '1994-08-09', 'sexo' => 'female',
    'telefono' => '+502 5123 4567', 'documento' => ['tipo' => 'DPI', 'numero' => '2233445560101']];

$pasos = [
    ['Admisión intenta registrar a Sofía como protegida', new Solicitud('POST', '/api/v1/pacientes', $cab('token-admision-oriente'), [], $sofia + ['identidad_protegida' => true, 'motivo' => 'Orden judicial'])],
    ['El supervisor la registra protegida, con motivo', new Solicitud('POST', '/api/v1/pacientes', $cab('token-supervisor-oriente'), [], $sofia + ['identidad_protegida' => true, 'motivo' => 'Orden judicial de protección'])],
    ['El clínico la busca por nombre', new Solicitud('GET', '/api/v1/pacientes', $cab('token-clinico-oriente'), ['nombre' => 'morales'])],
    ['El clínico la busca por código', new Solicitud('GET', '/api/v1/pacientes', $cab('token-clinico-oriente'), ['codigo' => 'P-000005'])],
    ['El clínico abre su detalle (para atenderla)', new Solicitud('GET', '/api/v1/pacientes/P-000005', $cab('token-clinico-oriente'))],
    ['Admisión intenta registrarla otra vez con su DPI', new Solicitud('POST', '/api/v1/pacientes', $cab('token-admision-oriente'), [], $sofia + ['nombres' => 'Sofi'])],
    ['Admisión intenta editarla', new Solicitud('PUT', '/api/v1/pacientes/P-000005', $cab('token-admision-oriente', ['If-Match' => '"1"']), [], $sofia)],
    ['El supervisor ve el detalle completo', new Solicitud('GET', '/api/v1/pacientes/P-000005', $cab('token-supervisor-oriente'))],
];

echo 'Micro-HIS Pacientes - demo del cambio practico: identidad protegida (PACIENTES_PERSISTENCIA=memoria)' . PHP_EOL;
foreach ($pasos as $i => [$titulo, $solicitud]) {
    $r = $kernel->manejar($solicitud);
    echo PHP_EOL . sprintf('%d. %s', $i + 1, $titulo) . PHP_EOL;
    echo sprintf('   %s %s -> %d', $solicitud->metodo, $solicitud->ruta, $r->estado) . PHP_EOL;
    echo '   ' . json_encode($r->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

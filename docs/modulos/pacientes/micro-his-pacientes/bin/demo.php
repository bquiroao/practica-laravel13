<?php

declare(strict_types=1);

/*
 * Recorrido de punta a punta SIN base de datos: la misma API (Kernel → controlador →
 * caso de uso → dominio → repositorio) con los adaptadores InMemory.
 *   php bin/demo.php
 */

use Pacientes\Presentacion\Http\Solicitud;

require dirname(__DIR__) . '/bootstrap/contenedor.php';

$config = ['persistencia' => 'memoria'] + (require dirname(__DIR__) . '/config/config.php');
$kernel = crearKernel($config);

$cabeceras = static fn (string $token, array $extra = []): array => $extra + ['X-Tenant-ID' => 'hosp-oriente', 'Authorization' => 'Bearer ' . $token];
$ana = ['nombres' => 'Ana Lucía', 'apellidos' => 'López Pérez', 'fecha_nacimiento' => '1985-03-14', 'sexo' => 'female',
    'telefono' => '+502 4444 0001', 'documento' => ['tipo' => 'DPI', 'numero' => '2547896320101']];
$nuevo = ['nombres' => 'Pedro', 'apellidos' => 'Ramírez Soto', 'fecha_nacimiento' => '1990-05-20', 'sexo' => 'male',
    'documento' => ['tipo' => 'DPI', 'numero' => '3012457890113']];

$pasos = [
    ['Registrar un paciente nuevo', new Solicitud('POST', '/api/v1/pacientes', $cabeceras('token-admision-oriente'), [], $nuevo)],
    ['Registrar otra vez el DPI de Ana (ya existe)', new Solicitud('POST', '/api/v1/pacientes', $cabeceras('token-admision-oriente'), [], $ana + ['nombres' => 'Ana'])],
    ['El clínico intenta registrar', new Solicitud('POST', '/api/v1/pacientes', $cabeceras('token-clinico-oriente'), [], $nuevo)],
    ['Buscar «lopez»', new Solicitud('GET', '/api/v1/pacientes', $cabeceras('token-clinico-oriente'), ['nombre' => 'lopez'])],
    ['Ver el detalle de P-000001', new Solicitud('GET', '/api/v1/pacientes/P-000001', $cabeceras('token-clinico-oriente'))],
    ['Actualizar sin If-Match', new Solicitud('PUT', '/api/v1/pacientes/P-000001', $cabeceras('token-admision-oriente'), [], $ana)],
    ['Actualizar con If-Match "1"', new Solicitud('PUT', '/api/v1/pacientes/P-000001', $cabeceras('token-admision-oriente', ['If-Match' => '"1"']), [], $ana)],
    ['Otro usuario actualiza con la versión vieja "1"', new Solicitud('PUT', '/api/v1/pacientes/P-000001', $cabeceras('token-admision-oriente', ['If-Match' => '"1"']), [], $ana)],
    ['Token de Norte con X-Tenant-ID de Oriente', new Solicitud('GET', '/api/v1/pacientes/P-000001', $cabeceras('token-admision-norte'))],
];

echo 'Micro-HIS Pacientes - demo sin base de datos (PACIENTES_PERSISTENCIA=memoria)' . PHP_EOL;
foreach ($pasos as $i => [$titulo, $solicitud]) {
    $r = $kernel->manejar($solicitud);
    $etag = isset($r->cabeceras['ETag']) ? '  ETag ' . $r->cabeceras['ETag'] : '';
    echo PHP_EOL . sprintf('%d. %s', $i + 1, $titulo) . PHP_EOL;
    echo sprintf('   %s %s -> %d%s', $solicitud->metodo, $solicitud->ruta, $r->estado, $etag) . PHP_EOL;
    echo '   ' . json_encode($r->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

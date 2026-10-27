<?php

declare(strict_types=1);

/*
 * Demo de punta a punta SIN base de datos: el mismo kernel de producción con los adaptadores InMemory.
 *   php bin/demo.php
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';

use Prescripciones\Presentacion\Http\Solicitud;

$config = require dirname(__DIR__) . '/config/config.php';
$config['persistencia'] = 'memoria';
$kernel = crearKernel($config);

$item = static fn (string $m, float|int $dosis = 500, int $cada = 8): array => ['medicamento' => $m, 'dosis' => $dosis, 'unidad' => 'mg',
    'frecuencia_horas' => $cada, 'via' => 'oral', 'dias' => 5];
$justificacion = 'Sin alternativa: cultivo sensible solo a cefalosporinas';
$pasos = [
    ['Verificación previa P-002 (penicilina) + cefalexina', 'POST', '/api/v1/prescripciones/verificaciones', 'token-dr-lopez', null,
        ['paciente' => 'P-002', 'items' => [$item('CEFA500')]]],
    ['Emitir sin justificación', 'POST', '/api/v1/prescripciones', 'token-dr-lopez', 'demo-1', ['paciente' => 'P-002', 'items' => [$item('CEFA500')]]],
    ['Emitir con justificación (dr-ruiz no tiene override)', 'POST', '/api/v1/prescripciones', 'token-dr-ruiz', 'demo-2',
        ['paciente' => 'P-002', 'items' => [$item('CEFA500')], 'justificacion' => $justificacion]],
    ['Emitir con justificación (dr-lopez, con override)', 'POST', '/api/v1/prescripciones', 'token-dr-lopez', 'demo-3',
        ['paciente' => 'P-002', 'items' => [$item('CEFA500')], 'justificacion' => $justificacion]],
    ['Reintento con la misma Idempotency-Key', 'POST', '/api/v1/prescripciones', 'token-dr-lopez', 'demo-3',
        ['paciente' => 'P-002', 'items' => [$item('CEFA500')], 'justificacion' => $justificacion]],
    ['Amoxicilina a P-001 (alergia directa)', 'POST', '/api/v1/prescripciones', 'token-dr-lopez', 'demo-4', ['paciente' => 'P-001', 'items' => [$item('AMOX500')]]],
    ['P-003 sin alergias evaluadas y sin confirmar', 'POST', '/api/v1/prescripciones', 'token-dr-lopez', 'demo-5', ['paciente' => 'P-003', 'items' => [$item('AMOX500')]]],
    ['Farmacia consulta la prescripción 1', 'GET', '/api/v1/prescripciones/1', 'token-farmacia-norte', null, null],
];

echo 'Micro-HIS Prescripciones · demo sin base (PRESCRIPCIONES_PERSISTENCIA=memoria)' . PHP_EOL;
foreach ($pasos as $n => [$titulo, $metodo, $ruta, $token, $clave, $cuerpo]) {
    $cabeceras = ['authorization' => "Bearer $token", 'x-tenant-id' => 'hosp-norte'] + ($clave !== null ? ['idempotency-key' => $clave] : []);
    $r = $kernel->manejar(new Solicitud($metodo, $ruta, $cabeceras, $cuerpo === null ? '' : (string) json_encode($cuerpo)));
    printf("\n%d. %s\n   %s %s → %s%s\n   %s\n",$n + 1, $titulo, $metodo, $ruta, $r->lineaDeEstado(),
        isset($r->cabeceras['Location']) ? ' · Location: ' . $r->cabeceras['Location'] : '',
        json_encode($r->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
}

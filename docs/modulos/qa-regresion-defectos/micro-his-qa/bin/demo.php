<?php

declare(strict_types=1);

/*
 * Recorrido del flujo SIN base de datos: el mismo Kernel, controladores y casos de uso que producción, con los
 * adaptadores en memoria (QA_PERSISTENCIA=memoria). Corre en un solo proceso porque la memoria no sobrevive entre
 * peticiones de «php -S».
 *   php bin/demo.php
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';

use Qa\Presentacion\Http\Solicitud;

$kernel = crearKernel(['persistencia' => 'memoria', 'registro_acceso' => 'ninguno'] + (require dirname(__DIR__) . '/config/config.php'));
$png = base64_encode("\x89PNG\r\n\x1a\nnota-firmada-editable");

$pasos = [
    ['Planificar con la matriz de «expediente»', 'POST', '/campanas', 'token-lider-qa', ['nombre' => 'Regresión H4', 'funcionalidades' => ['expediente'], 'casos' => ['TC-107', 'TC-108']]],
    ['Iniciar sobre 1.4.0', 'POST', '/campanas/1/inicio', 'token-lider-qa', ['build' => '1.4.0']],
    ['FALLA de TC-107 con captura y defecto', 'POST', '/campanas/1/resultados', 'token-tito-tester', ['caso' => 'TC-107', 'resultado' => 'FALLA',
        'evidencias' => [['tipo' => 'captura', 'contenido_base64' => $png]], 'defecto' => ['titulo' => 'Nota firmada editable', 'pasos' => ['Abrir', 'Editar'], 'severidad' => 'CRITICA']], 'demo-mem-001'],
    ['Entrada mal formada (resultado como lista): 400 sin tocar nada', 'POST', '/campanas/1/resultados', 'token-tito-tester', ['caso' => 'TC-108', 'resultado' => ['PASA']], 'demo-mem-002'],
    ['TC-108 pasa', 'POST', '/campanas/1/resultados', 'token-tito-tester', ['caso' => 'TC-108', 'resultado' => 'PASA'], 'demo-mem-003'],
    ['Confirmar CRÍTICA', 'POST', '/defectos/1/clasificacion', 'token-lider-qa', ['severidad' => 'CRITICA', 'prioridad' => 'ALTA']],
    ['Cerrar: NO_GO', 'POST', '/campanas/1/cierre', 'token-lider-qa', null],
    ['Trazabilidad', 'GET', '/campanas/1/trazabilidad', 'token-producto', null],
];
foreach ($pasos as $n => [$titulo, $metodo, $ruta, $token, $cuerpo]) {
    $clave = $pasos[$n][5] ?? null;
    $r = $kernel->manejar(new Solicitud($metodo, '/api/v1/qa' . $ruta, ['authorization' => "Bearer $token", 'x-tenant-id' => 'hosp-norte']
        + ($clave === null ? [] : ['idempotency-key' => $clave]), $cuerpo === null ? '' : json_encode($cuerpo)));
    $json = json_encode($r->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    printf("%d. %s\n   > %s /api/v1/qa%s\n   %d %s\n\n", $n + 1, $titulo, $metodo, $ruta, $r->estado, strlen($json) > 220 ? substr($json, 0, 217) . '...' : $json);
}
